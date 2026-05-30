<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ProxySeverity;
use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Events\AttendanceMarked;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\DeviceRegistration;
use App\Models\Enrollment;
use App\Models\ProxyFlag;
use App\Models\SecurityPolicy;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceScanService
{
    public function __construct(
        private QRChallengeService $qrService,
    ) {}

    /**
     * Run the full QR scan pipeline for a student.
     *
     * @return array{status: string, message: string, attendance_record_id: int|null}
     *
     * @throws ValidationException
     */
    public function scan(
        Student $student,
        string $qrPayload,
        string $deviceFingerprint,
        float $latitude,
        float $longitude,
    ): array {
        return DB::transaction(function () use ($student, $qrPayload, $deviceFingerprint, $latitude, $longitude) {
            // Step 1 — Decode payload
            $decoded = json_decode(base64_decode($qrPayload), true);
            if (! is_array($decoded) || empty($decoded['session_uuid']) || ! is_numeric($decoded['issued_at'] ?? null)) {
                throw ValidationException::withMessages(['qr_payload' => ['Invalid QR code.']]);
            }

            // Step 2 — Find active session
            $session = AttendanceSession::where('uuid', $decoded['session_uuid'])
                ->where('status', SessionStatus::Active)
                ->first();
            if (! $session) {
                throw ValidationException::withMessages(['qr_payload' => ['Session not found or not active.']]);
            }

            // Step 3 — Validate QR signature and expiry
            if (! $this->qrService->validateScan($qrPayload, $session)) {
                throw ValidationException::withMessages(['qr_payload' => ['QR code expired or invalid.']]);
            }

            // Step 4 — Check enrollment
            $enrollment = Enrollment::where('student_id', $student->id)
                ->where('course_id', $session->course_id)
                ->where('status', EnrollmentStatus::Active)
                ->first();
            if (! $enrollment) {
                throw ValidationException::withMessages(['qr_payload' => ['Student is not enrolled in this course.']]);
            }

            // Step 5 — Duplicate check (lockForUpdate prevents concurrent scans from both passing)
            if (AttendanceRecord::where('session_id', $session->id)->where('student_id', $student->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['qr_payload' => ['Attendance already recorded for this session.']]);
            }

            // Step 6 — Device check
            $device = DeviceRegistration::where('user_id', $student->user_id)
                ->where('device_fingerprint', $deviceFingerprint)
                ->first();
            if (! $device) {
                throw ValidationException::withMessages(['device_fingerprint' => ['Device not registered.']]);
            }

            $policy = SecurityPolicy::getActive();
            if (! $policy) {
                throw ValidationException::withMessages(['qr_payload' => ['System security policy is not configured.']]);
            }
            if ($policy->device_binding_required && ! $device->is_trusted) {
                throw ValidationException::withMessages(['device_fingerprint' => ['Device not authorised for this student.']]);
            }

            // Step 7 — Risk scoring
            $riskScore = 0;
            $triggeredFactors = [];
            $room = $session->room;
            $gpsDistanceM = null;

            if ($room?->latitude && $room?->longitude && $room?->geofence_radius_m) {
                $gpsDistanceM = $this->haversineDistance(
                    $latitude,
                    $longitude,
                    (float) $room->latitude,
                    (float) $room->longitude,
                );
                if ($gpsDistanceM > $room->geofence_radius_m) {
                    $riskScore += $policy->w_gps;
                    $triggeredFactors['gps_mismatch'] = $policy->w_gps;
                }
            }

            if (! $device->is_trusted) {
                $riskScore += $policy->w_device;
                $triggeredFactors['device_mismatch'] = $policy->w_device;
            }

            $qrAgeSeconds = now()->timestamp - $decoded['issued_at'];
            if ($qrAgeSeconds > $policy->qr_expiry_seconds / 2) {
                $riskScore += $policy->w_clock_skew;
                $triggeredFactors['clock_skew'] = $policy->w_clock_skew;
            }

            $riskScore = min(100, $riskScore);

            $evidenceJson = [
                'gps_distance_m' => $gpsDistanceM,
                'device_trusted' => $device->is_trusted,
                'qr_age_seconds' => $qrAgeSeconds,
                'weights' => [
                    'gps' => $policy->w_gps,
                    'device' => $policy->w_device,
                    'clock_skew' => $policy->w_clock_skew,
                ],
            ];

            // Step 8 — Auto-reject
            if ($riskScore >= $policy->risk_auto_reject) {
                return [
                    'status' => 'rejected',
                    'message' => 'Scan rejected due to elevated risk score.',
                    'attendance_record_id' => null,
                ];
            }

            // Step 9 — Determine status (PendingReview beats Late)
            $status = AttendanceStatus::Present;
            if (now()->isAfter($session->started_at->addMinutes($policy->late_threshold_mins))) {
                $status = AttendanceStatus::Late;
            }
            if ($riskScore >= $policy->risk_pending_review) {
                $status = AttendanceStatus::PendingReview;
            }

            // Step 10 — Create AttendanceRecord
            $record = AttendanceRecord::create([
                'session_id' => $session->id,
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'device_id' => $device->id,
                'status' => $status,
                'marked_at' => now(),
                'risk_score' => $riskScore,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'evidence_json' => $evidenceJson,
            ]);

            // Step 11 — Create ProxyFlag when pending review
            if ($status === AttendanceStatus::PendingReview) {
                ProxyFlag::create([
                    'attendance_record_id' => $record->id,
                    'severity' => $this->resolveSeverity($riskScore),
                    'reason_code' => $this->resolveReasonCode($triggeredFactors),
                    'risk_score' => $riskScore,
                    'evidence_json' => $evidenceJson,
                    'review_status' => ReviewStatus::Pending,
                ]);
            }

            // Step 12 — Broadcast
            broadcast(new AttendanceMarked(
                session: $session,
                studentName: $student->user->name,
                status: $status->value,
                riskScore: $riskScore,
                markedAt: now()->toISOString(),
                sessionStats: [
                    'total_present' => $session->attendanceRecords()->where('status', AttendanceStatus::Present)->count(),
                    'total_late' => $session->attendanceRecords()->where('status', AttendanceStatus::Late)->count(),
                    'total_absent' => max(0, $session->total_enrolled - $session->attendanceRecords()->count()),
                ],
            ));

            // Step 13 — Return
            return [
                'status' => $status->value,
                'message' => 'Attendance recorded successfully.',
                'attendance_record_id' => $record->id,
            ];
        });
    }

    /** Haversine distance in metres between two GPS coordinates. */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Map risk score to ProxySeverity: 50-59 Low, 60-69 Medium, 70-79 High, 80+ Critical.
     */
    private function resolveSeverity(int $riskScore): ProxySeverity
    {
        return match (true) {
            $riskScore >= 80 => ProxySeverity::Critical,
            $riskScore >= 70 => ProxySeverity::High,
            $riskScore >= 60 => ProxySeverity::Medium,
            default => ProxySeverity::Low,
        };
    }

    /**
     * Return the reason code of the highest-priority triggered factor.
     * Priority: gps_mismatch > device_mismatch > clock_skew.
     */
    private function resolveReasonCode(array $triggeredFactors): string
    {
        foreach (['gps_mismatch', 'device_mismatch', 'clock_skew'] as $code) {
            if (isset($triggeredFactors[$code])) {
                return $code;
            }
        }

        return 'unknown';
    }
}
