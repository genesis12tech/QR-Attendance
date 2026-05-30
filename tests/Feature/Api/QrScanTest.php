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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

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
    ksort($inner);
    $hmac = hash_hmac('sha256', json_encode($inner), config('services.qr_secret'));
    $payload = base64_encode(json_encode(array_merge($inner, ['hmac' => $hmac])));

    Cache::put("qr:{$session->uuid}:{$nonce}", $payload, 60);

    return $payload;
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
        'course_id' => $course->id,
        'class_group_id' => $classGroup->id,
    ]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'class_group_id' => $classGroup->id,
        'status' => EnrollmentStatus::Active,
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
            'qr_payload' => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 0.0,
            'longitude' => 0.0,
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
            'qr_payload' => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 0.0,
            'longitude' => 0.0,
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
            'qr_payload' => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 0.0,
            'longitude' => 0.0,
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
            'qr_payload' => $tamperedPayload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 0.0,
            'longitude' => 0.0,
        ])
        ->assertUnprocessable();
});

// ── Device rejection ─────────────────────────────────────────────────────────

it('rejects a scan with an unregistered device fingerprint', function () {
    $f = scanFixture();
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload' => $payload,
            'device_fingerprint' => 'unknown-fingerprint-xyz',
            'latitude' => 0.0,
            'longitude' => 0.0,
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
            'qr_payload' => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 0.0,
            'longitude' => 0.0,
        ])
        ->assertUnprocessable();
});

// ── Risk scoring ─────────────────────────────────────────────────────────────

it('raises the risk score when the student is outside the geofence', function () {
    $f = scanFixture();
    // Room in London, student ~1.4 km away — well outside 50 m geofence
    $room = Room::factory()->create([
        'latitude' => 51.5074,
        'longitude' => -0.1278,
        'geofence_radius_m' => 50,
    ]);
    $f['session']->update(['room_id' => $room->id]);
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload' => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 51.5200,
            'longitude' => -0.1278,
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
        'latitude' => 51.5074,
        'longitude' => -0.1278,
        'geofence_radius_m' => 50,
    ]);
    $f['session']->update(['room_id' => $room->id]);
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload' => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 51.5200,
            'longitude' => -0.1278,
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
        'latitude' => 51.5074,
        'longitude' => -0.1278,
        'geofence_radius_m' => 50,
    ]);
    $f['session']->update(['room_id' => $room->id]);
    $payload = buildQrPayload($f['session']);

    $this->actingAs($f['user'], 'sanctum')
        ->postJson('/api/v1/student/attendance/scan', [
            'qr_payload' => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 51.5200,
            'longitude' => -0.1278,
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
            'qr_payload' => $payload,
            'device_fingerprint' => $f['device']->device_fingerprint,
            'latitude' => 0.0,
            'longitude' => 0.0,
        ])
        ->assertOk();

    Event::assertDispatched(AttendanceMarked::class);
});
