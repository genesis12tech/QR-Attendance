# Phase 7.2 — QR Scan Endpoint Design

**Date:** 2026-05-30
**Scope:** `POST /api/v1/student/attendance/scan` — the core endpoint a student app calls after scanning a rotating QR code in the classroom.

---

## 1. Architecture

Two new files:

| File | Purpose |
|---|---|
| `app/Http/Controllers/Api/V1/Student/ScanController.php` | Thin HTTP handler: validate request, call service, return JSON |
| `app/Services/AttendanceScanService.php` | Owns the full 11-step scan pipeline inside a DB transaction |

This mirrors the existing `QRChallengeService` pattern and keeps the controller thin. A single test file covers the endpoint end-to-end: `tests/Feature/Api/QrScanTest.php`.

---

## 2. Route

Added to `routes/api.php` inside the existing `auth:sanctum` middleware group:

```php
Route::post('attendance/scan', [ScanController::class, 'store'])
    ->middleware('throttle:10,1');
```

Rate limit: 10 requests per minute per student.

---

## 3. Request

`POST /api/v1/student/attendance/scan`

| Field | Type | Required | Notes |
|---|---|---|---|
| `qr_payload` | string | yes | Base64-encoded JSON from the QR image |
| `device_fingerprint` | string | yes | Must match a registered device for this student |
| `latitude` | numeric | yes | Student's current GPS latitude |
| `longitude` | numeric | yes | Student's current GPS longitude |

---

## 4. Scan Pipeline (`AttendanceScanService::scan()`)

Runs inside a single `DB::transaction()`. Steps are executed in order; any hard rejection throws before the transaction commits.

### Step 1 — Decode payload
Base64-decode `qr_payload`, JSON-parse, extract `session_uuid`, `issued_at`, `hmac`, `nonce`. Return 422 if malformed.

### Step 2 — Find active session
`AttendanceSession::where('uuid', $sessionUuid)->where('status', SessionStatus::Active)->first()`. Return 422 "Session not found or not active" if absent.

### Step 3 — Validate QR
Call `QRChallengeService::validateScan($qrPayload, $session)`. Return 422 "QR code expired or invalid" if it returns false.

### Step 4 — Check enrollment
Find active `Enrollment` for (`student_id`, `course_id` via session). Return 422 "Student is not enrolled in this course" if absent.

### Step 5 — Duplicate check
`AttendanceRecord::where('session_id', $session->id)->where('student_id', $student->id)->exists()`. Return 422 "Attendance already recorded for this session".

### Step 6 — Device check
Find `DeviceRegistration` where `device_fingerprint = $request->device_fingerprint AND user_id = $student->user_id`.
- Not found → 422 "Device not registered"
- Found but `is_trusted = false` AND `policy->device_binding_required = true` → 422 "Device not authorised for this student"

### Step 7 — Risk scoring
`risk_score` starts at 0, capped at 100. Each triggered factor adds its weight from `SecurityPolicy::getActive()`:

| Factor | Condition | Weight column |
|---|---|---|
| GPS mismatch | Session room has geofence coordinates AND haversine distance > `geofence_radius_m` | `w_gps` |
| Device not trusted | Device `is_trusted = false` (only reached if `device_binding_required = false`) | `w_device` |
| Clock skew | QR age (`now()->timestamp - issued_at`) > `qr_expiry_seconds / 2` | `w_clock_skew` |

`$riskScore = min(100, $gpsWeight + $deviceWeight + $clockSkewWeight)`

Haversine formula used to compute distance in metres between student coordinates and room coordinates.

### Step 8 — Auto-reject
If `risk_score >= policy->risk_auto_reject`: return 200 `{status: "rejected", message: "Scan rejected due to elevated risk score"}`. No record created.

Reason for 200 (not 422): the student app needs a clean parseable response to display "rejected" without treating it as a validation error.

### Step 9 — Determine status
```
status = AttendanceStatus::Present
if now() > session->started_at->addMinutes(policy->late_threshold_mins):
    status = AttendanceStatus::Late
if risk_score >= policy->risk_pending_review:
    status = AttendanceStatus::PendingReview
```

`PendingReview` takes precedence over `Late`.

### Step 10 — Create AttendanceRecord
```php
AttendanceRecord::create([
    'session_id'    => $session->id,
    'student_id'    => $student->id,
    'enrollment_id' => $enrollment->id,
    'device_id'     => $device->id,
    'status'        => $status,
    'marked_at'     => now(),
    'risk_score'    => $riskScore,
    'latitude'      => $request->latitude,
    'longitude'     => $request->longitude,
    'evidence_json' => $evidenceJson,
])
```

`evidence_json` = `{gps_distance_m, device_trusted, qr_age_seconds, weights: {gps, device, clock_skew}}`.

### Step 11 — Create ProxyFlag (if pending_review)
```php
ProxyFlag::create([
    'attendance_record_id' => $record->id,
    'severity'             => $severity,       // mapped from risk_score
    'reason_code'          => $reasonCode,     // highest-weighted triggered factor
    'risk_score'           => $riskScore,
    'evidence_json'        => $evidenceJson,
    'review_status'        => ReviewStatus::Pending,
])
```

**Severity mapping:**

| Risk score | Severity |
|---|---|
| 50–59 | Low |
| 60–69 | Medium |
| 70–79 | High |
| 80+ | Critical |

**Reason code** = the triggered factor with the highest weight. Possible values (matching existing factory): `gps_mismatch`, `device_mismatch`, `clock_skew`. If tied, prefer `gps_mismatch` > `device_mismatch` > `clock_skew`.

### Step 12 — Broadcast
```php
broadcast(new AttendanceMarked(
    session: $session,
    studentName: $student->user->name,
    status: $status->value,
    riskScore: $riskScore,
    markedAt: now()->toISOString(),
    sessionStats: [
        'total_present' => $session->attendanceRecords()->where('status', AttendanceStatus::Present)->count(),
        'total_late'    => $session->attendanceRecords()->where('status', AttendanceStatus::Late)->count(),
        'total_absent'  => $session->total_enrolled - $session->attendanceRecords()->count(),
    ],
));
```

### Step 13 — Return
```json
{
  "status": "present",
  "message": "Attendance recorded successfully.",
  "attendance_record_id": 42
}
```

---

## 5. Error Responses

All hard rejections use 422 with `{message: "..."}` JSON body — consistent with `AuthController` and `DeviceController`. The one exception is auto-reject (step 8), which returns 200 so the student app can distinguish a processed-but-rejected scan from a validation failure.

---

## 6. Tests (`tests/Feature/Api/QrScanTest.php`)

Each test uses `actingAs($user, 'sanctum')`. A shared helper builds the standard fixture: active session + enrolled student + registered device + valid QR payload.

| Test | Scenario |
|---|---|
| `test_valid_scan_creates_attendance_record_with_status_present` | Happy path — asserts DB record and 200 `present` |
| `test_scan_after_late_threshold_creates_record_with_status_late` | `started_at = now() - late_threshold - 1min` |
| `test_expired_qr_payload_is_rejected` | `issued_at` far in the past, `validateScan` returns false |
| `test_tampered_qr_payload_is_rejected` | HMAC altered; `validateScan` returns false |
| `test_scan_with_unregistered_device_is_rejected` | Fingerprint not in `device_registrations` |
| `test_scan_outside_geofence_raises_risk_score` | Room has geofence; student coordinates outside; assert `risk_score > 0` on record |
| `test_high_risk_scan_creates_proxy_flag` | Set `w_gps` + `w_device` high so risk ≥ `risk_pending_review`; assert `ProxyFlag` created |
| `test_auto_reject_threshold_skips_record_creation` | Risk ≥ `risk_auto_reject`; assert no `AttendanceRecord`, response `status = "rejected"` |
| `test_duplicate_scan_for_same_session_is_rejected` | Pre-create a record; expect 422 |
| `test_scan_broadcasts_attendance_marked_event` | `Event::fake()`; assert `AttendanceMarked` dispatched with correct payload |
