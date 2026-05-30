# Phase 7.2 — QR Scan Endpoint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement `POST /api/v1/student/attendance/scan` — the endpoint a student app calls after scanning a rotating QR code, running an 11-step security pipeline that creates an `AttendanceRecord`, optionally a `ProxyFlag`, and broadcasts a live update.

**Architecture:** Thin `ScanController` delegates to `AttendanceScanService`, which owns the full pipeline inside a single `DB::transaction()`. Hard rejections throw `ValidationException` (→ 422). Auto-reject returns 200 with `status: "rejected"` so the student app can distinguish a processed rejection from a validation error.

**Tech Stack:** Laravel 12, Sanctum, `QRChallengeService` (existing), `AttendanceMarked` broadcast event (existing), Pest 3 feature tests.

**PHP binary:** `/Users/thomas/.config/herd-lite/bin/php`

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Modify | `routes/api.php` | Add scan route under `auth:sanctum` |
| Create | `app/Http/Controllers/Api/V1/Student/ScanController.php` | Validate 4 fields, delegate to service, return JSON |
| Create | `app/Services/AttendanceScanService.php` | 11-step scan pipeline in one DB transaction |
| Create | `tests/Feature/Api/QrScanTest.php` | 10 feature tests covering all paths |

---

## Task 1: Test file + route

**Files:**
- Create: `tests/Feature/Api/QrScanTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create the test file**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest "Api/QrScanTest" --no-interaction
```

- [ ] **Step 2: Replace the generated file with the complete test suite**

Write the following to `tests/Feature/Api/QrScanTest.php`:

```php
<?php

use App\Enums\EnrollmentStatus;
use App\Events\AttendanceMarked;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Department;
use App\Models\DeviceRegistration;
use App\Models\Enrollment;
use App\Models\ProxyFlag;
use App\Models\Room;
use App\Models\SecurityPolicy;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(TestCase::class, LazilyRefreshDatabase::class);

/**
 * Build a valid base64-encoded QR payload for the given session.
 * Pass an explicit $issuedAt (Unix timestamp) to simulate expired payloads.
 */
function buildQrPayload(AttendanceSession $session, ?int $issuedAt = null): string
{
    $nonce = (string) Str::uuid();
    $issuedAt ??= now()->timestamp;
    $inner = ['session_uuid' => $session->uuid, 'nonce' => $nonce, 'issued_at' => $issuedAt];
    $hmac = hash_hmac('sha256', json_encode($inner), config('services.qr_secret'));

    return base64_encode(json_encode(array_merge($inner, ['hmac' => $hmac])));
}

/**
 * Standard fixture: active session + enrolled student + trusted device + active policy.
 */
function scanFixture(): array
{
    $department = Department::factory()->create();
    $course = Course::factory()->create(['department_id' => $department->id]);
    $classGroup = ClassGroup::factory()->create(['course_id' => $course->id]);
    $user = User::factory()->student()->create();
    $student = Student::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
    $device = DeviceRegistration::factory()->trusted()->create(['user_id' => $user->id]);
    $session = AttendanceSession::factory()->active()->create([
        'course_id'      => $course->id,
        'class_group_id' => $classGroup->id,
    ]);
    Enrollment::factory()->create([
        'student_id'     => $student->id,
        'course_id'      => $course->id,
        'class_group_id' => $classGroup->id,
        'status'         => EnrollmentStatus::Active,
    ]);
    SecurityPolicy::factory()->create();
    Cache::forget('security_policy.active');

    return compact('user', 'student', 'device', 'session', 'course', 'classGroup');
}

// ── Validation ───────────────────────────────────────────────────────────────

it('requires all four fields to scan', function () {
    $user = User::factory()->student()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qr_payload', 'device_fingerprint', 'latitude', 'longitude']);
});

// ── Happy path ───────────────────────────────────────────────────────────────

it('creates an attendance record with status present for a valid scan', function () {
    $f = scanFixture();
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 0.0,
            'longitude'          => 0.0,
        ])
        ->assertOk()
        ->assertJsonStructure(['status', 'message', 'attendance_record_id'])
        ->assertJsonPath('status', 'present');

    expect(AttendanceRecord::where('student_id', $f['student']->id)->exists())->toBeTrue();
});

it('creates an attendance record with status late when scan is after the late threshold', function () {
    $f = scanFixture();
    // Move started_at 20 minutes back; late_threshold_mins = 10
    $f['session']->update(['started_at' => now()->subMinutes(20)]);
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 0.0,
            'longitude'          => 0.0,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'late');
});

// ── QR rejections ────────────────────────────────────────────────────────────

it('rejects an expired qr payload', function () {
    $f = scanFixture();
    $policy = SecurityPolicy::getActive();
    // issued_at well outside the expiry + clock_skew window
    $oldIssuedAt = now()->timestamp - $policy->qr_expiry_seconds - $policy->clock_skew_seconds - 100;
    $payload = buildQrPayload($f['session'], $oldIssuedAt);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 0.0,
            'longitude'          => 0.0,
        ])
        ->assertUnprocessable();
});

it('rejects a tampered qr payload', function () {
    $f = scanFixture();
    $decoded = json_decode(base64_decode(buildQrPayload($f['session'])), true);
    $decoded['hmac'] = 'tampered_hmac_value';
    $tamperedPayload = base64_encode(json_encode($decoded));

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $tamperedPayload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 0.0,
            'longitude'          => 0.0,
        ])
        ->assertUnprocessable();
});

// ── Device rejection ─────────────────────────────────────────────────────────

it('rejects a scan with an unregistered device fingerprint', function () {
    $f = scanFixture();
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => 'unknown-fingerprint-xyz',
            'latitude'           => 0.0,
            'longitude'          => 0.0,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_fingerprint']);
});

// ── Duplicate rejection ──────────────────────────────────────────────────────

it('rejects a duplicate scan for the same session', function () {
    $f = scanFixture();
    AttendanceRecord::factory()->create([
        'session_id' => $f['session']->id,
        'student_id' => $f['student']->id,
    ]);
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 0.0,
            'longitude'          => 0.0,
        ])
        ->assertUnprocessable();
});

// ── Risk scoring ─────────────────────────────────────────────────────────────

it('raises the risk score when the student is outside the geofence', function () {
    $f = scanFixture();
    // Room in London, student ~1.4 km away — well outside 50 m geofence
    $room = Room::factory()->create([
        'latitude'          => 51.5074,
        'longitude'         => -0.1278,
        'geofence_radius_m' => 50,
    ]);
    $f['session']->update(['room_id' => $room->id]);
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 51.5200,
            'longitude'          => -0.1278,
        ])
        ->assertOk();

    $record = AttendanceRecord::where('student_id', $f['student']->id)->first();
    expect($record->risk_score)->toBeGreaterThan(0);
});

// ── Auto-reject ──────────────────────────────────────────────────────────────

it('returns rejected status and creates no record when risk meets the auto reject threshold', function () {
    $f = scanFixture();
    // w_gps = 80, risk_auto_reject = 80 → risk_score (80) >= threshold (80) → auto-reject
    SecurityPolicy::query()->update(['w_gps' => 80]);
    Cache::forget('security_policy.active');
    $room = Room::factory()->create([
        'latitude'          => 51.5074,
        'longitude'         => -0.1278,
        'geofence_radius_m' => 50,
    ]);
    $f['session']->update(['room_id' => $room->id]);
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 51.5200,
            'longitude'          => -0.1278,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'rejected');

    expect(AttendanceRecord::where('student_id', $f['student']->id)->exists())->toBeFalse();
});

// ── ProxyFlag ────────────────────────────────────────────────────────────────

it('creates a proxy flag for a high risk scan', function () {
    $f = scanFixture();
    // w_gps = 60 → risk (60) >= risk_pending_review (50) but < risk_auto_reject (80) → pending_review + flag
    SecurityPolicy::query()->update(['w_gps' => 60]);
    Cache::forget('security_policy.active');
    $room = Room::factory()->create([
        'latitude'          => 51.5074,
        'longitude'         => -0.1278,
        'geofence_radius_m' => 50,
    ]);
    $f['session']->update(['room_id' => $room->id]);
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 51.5200,
            'longitude'          => -0.1278,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'pending_review');

    $record = AttendanceRecord::where('student_id', $f['student']->id)->first();
    expect(ProxyFlag::where('attendance_record_id', $record->id)->exists())->toBeTrue();
});

// ── Broadcast ────────────────────────────────────────────────────────────────

it('broadcasts an attendance marked event on a successful scan', function () {
    Event::fake([AttendanceMarked::class]);
    $f = scanFixture();
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload'         => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude'           => 0.0,
            'longitude'          => 0.0,
        ])
        ->assertOk();

    Event::assertDispatched(AttendanceMarked::class);
});
```

- [ ] **Step 3: Add the route to `routes/api.php`**

Open `routes/api.php`. The current content is:

```php
<?php

use App\Http\Controllers\Api\V1\Student\AuthController;
use App\Http\Controllers\Api\V1\Student\DeviceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/student')->group(function () {
    Route::prefix('auth')->middleware('throttle:60,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
        Route::get('devices', [DeviceController::class, 'index']);
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{device}', [DeviceController::class, 'destroy']);
    });
});
```

Replace it with:

```php
<?php

use App\Http\Controllers\Api\V1\Student\AuthController;
use App\Http\Controllers\Api\V1\Student\DeviceController;
use App\Http\Controllers\Api\V1\Student\ScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/student')->group(function () {
    Route::prefix('auth')->middleware('throttle:60,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
        Route::get('devices', [DeviceController::class, 'index']);
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{device}', [DeviceController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('attendance/scan', [ScanController::class, 'store'])
            ->middleware('throttle:10,1');
    });
});
```

- [ ] **Step 4: Run just the validation test to confirm the route is registered (will fail — ScanController doesn't exist yet)**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter="requires all four fields"
```

Expected: FAIL with "Target class [App\Http\Controllers\Api\V1\Student\ScanController] does not exist" or similar.

---

## Task 2: ScanController

**Files:**
- Create: `app/Http/Controllers/Api/V1/Student/ScanController.php`

- [ ] **Step 1: Create the controller**

Write the following to `app/Http/Controllers/Api/V1/Student/ScanController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\AttendanceScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function __construct(
        private AttendanceScanService $scanService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_payload'         => ['required', 'string'],
            'device_fingerprint' => ['required', 'string'],
            'latitude'           => ['required', 'numeric'],
            'longitude'          => ['required', 'numeric'],
        ]);

        $result = $this->scanService->scan(
            student: $request->user()->student,
            qrPayload: $data['qr_payload'],
            deviceFingerprint: $data['device_fingerprint'],
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
        );

        return response()->json($result);
    }
}
```

- [ ] **Step 2: Run the validation test (will fail — AttendanceScanService doesn't exist yet)**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter="requires all four fields"
```

Expected: FAIL with "Target class [App\Services\AttendanceScanService] does not exist" or similar.

---

## Task 3: AttendanceScanService

**Files:**
- Create: `app/Services/AttendanceScanService.php`

- [ ] **Step 1: Create the service**

Write the following to `app/Services/AttendanceScanService.php`:

```php
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
            if (! is_array($decoded) || empty($decoded['session_uuid'])) {
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

            // Step 5 — Duplicate check
            if (AttendanceRecord::where('session_id', $session->id)->where('student_id', $student->id)->exists()) {
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
                'weights'        => [
                    'gps'        => $policy->w_gps,
                    'device'     => $policy->w_device,
                    'clock_skew' => $policy->w_clock_skew,
                ],
            ];

            // Step 8 — Auto-reject
            if ($riskScore >= $policy->risk_auto_reject) {
                return [
                    'status'               => 'rejected',
                    'message'              => 'Scan rejected due to elevated risk score.',
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
                'session_id'    => $session->id,
                'student_id'    => $student->id,
                'enrollment_id' => $enrollment->id,
                'device_id'     => $device->id,
                'status'        => $status,
                'marked_at'     => now(),
                'risk_score'    => $riskScore,
                'latitude'      => $latitude,
                'longitude'     => $longitude,
                'evidence_json' => $evidenceJson,
            ]);

            // Step 11 — Create ProxyFlag when pending review
            if ($status === AttendanceStatus::PendingReview) {
                ProxyFlag::create([
                    'attendance_record_id' => $record->id,
                    'severity'             => $this->resolveSeverity($riskScore),
                    'reason_code'          => $this->resolveReasonCode($triggeredFactors),
                    'risk_score'           => $riskScore,
                    'evidence_json'        => $evidenceJson,
                    'review_status'        => ReviewStatus::Pending,
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
                    'total_late'    => $session->attendanceRecords()->where('status', AttendanceStatus::Late)->count(),
                    'total_absent'  => max(0, $session->total_enrolled - $session->attendanceRecords()->count()),
                ],
            ));

            // Step 13 — Return
            return [
                'status'               => $status->value,
                'message'              => 'Attendance recorded successfully.',
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

    /** Map risk score to ProxySeverity: 50-59 Low, 60-69 Medium, 70-79 High, 80+ Critical. */
    private function resolveSeverity(int $riskScore): ProxySeverity
    {
        return match (true) {
            $riskScore >= 80 => ProxySeverity::Critical,
            $riskScore >= 70 => ProxySeverity::High,
            $riskScore >= 60 => ProxySeverity::Medium,
            default          => ProxySeverity::Low,
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
```

- [ ] **Step 2: Run the full QrScanTest suite**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=QrScanTest
```

Expected: all 10 tests pass. If any fail, read the failure message carefully before changing code — the most common issues are:
- `SecurityPolicy::getActive()` returns null → the fixture didn't create a SecurityPolicy. Check `scanFixture()` is called in that test.
- QR validation fails on the happy path → the `config('services.qr_secret')` value is empty. Both `buildQrPayload()` and `QRChallengeService::validateScan()` use the same config key, so they will always agree regardless of the value.
- Late threshold test not marking Late → verify `started_at` was updated on the session that the payload was built from (both must reference the same UUID).

---

## Task 4: Format + full test run + commit

**Files:**
- All PHP files modified in this feature

- [ ] **Step 1: Run Pint on the three new/modified PHP files**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
```

Expected: no errors. Any formatting changes are applied automatically.

- [ ] **Step 2: Run the full test suite**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
```

Expected: all tests pass (existing suites unaffected).

- [ ] **Step 3: Commit**

```bash
git add routes/api.php \
        app/Http/Controllers/Api/V1/Student/ScanController.php \
        app/Services/AttendanceScanService.php \
        tests/Feature/Api/QrScanTest.php

git commit -m "$(cat <<'EOF'
feat: implement Phase 7.2 — QR scan endpoint

POST /api/v1/student/attendance/scan runs an 11-step pipeline:
decode payload → find active session → validate QR → check enrollment
→ duplicate guard → device check → risk score (GPS/device/clock) →
auto-reject → determine status → create AttendanceRecord → create
ProxyFlag if pending_review → broadcast AttendanceMarked.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```
