# Phase 7.3 — Attendance History Endpoint Design

**Date:** 2026-05-30
**Scope:** Two read-only GET endpoints exposing a student's own attendance data to the mobile app.

---

## 1. Architecture

Single new controller following the existing thin-controller pattern:

| File | Purpose |
|---|---|
| `app/Http/Controllers/Api/V1/Student/AttendanceController.php` | `index()` + `summary()` methods |
| `tests/Feature/Api/AttendanceHistoryTest.php` | 4 feature tests |

`routes/api.php` gains two routes in the existing `auth:sanctum, throttle:30,1` group.

---

## 2. Routes

Added to the `['auth:sanctum', 'throttle:30,1']` middleware group in `routes/api.php`:

```
GET /api/v1/student/attendance         → AttendanceController@index
GET /api/v1/student/attendance/summary → AttendanceController@summary
```

---

## 3. `index()` — Paginated Record List

**Query:** `AttendanceRecord` scoped to `student_id = $student->id`, eager-loads `session.course`, ordered by `marked_at desc`. Paginated at 15 per page (Laravel default).

**Response shape** (standard Laravel paginator envelope — `data`, `links`, `meta`):

```json
{
  "data": [
    {
      "id": 42,
      "status": "present",
      "marked_at": "2026-05-30T09:15:00Z",
      "risk_score": 0,
      "session": {
        "started_at": "2026-05-30T09:00:00Z",
        "course_code": "CS-101",
        "course_name": "Introduction to Programming"
      }
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 15, "total": 42, ... }
}
```

`$request->user()->student` is guarded with `abort_unless($student !== null, 403)` — same pattern as `ScanController`.

---

## 4. `summary()` — Per-Course Breakdown

**Query:** For each active `Enrollment` belonging to the student, count `AttendanceRecord` rows for that course's sessions.

**Computed fields per course:**

| Field | Computation |
|---|---|
| `attended` | `COUNT` of records with `status IN (present, late)` |
| `total` | `COUNT` of all records (any status) for the course's sessions |
| `percentage` | `total > 0 ? round(attended / total * 100, 2) : 0.0` |
| `minimum_pct` | `course.min_attendance_pct` (cast as string by Eloquent `decimal:2`) |
| `below_minimum` | `percentage < (float) minimum_pct` |

**Implementation strategy:** Load active enrollments with `course`, then for each enrollment run two counts on `AttendanceRecord::where('student_id', $student->id)->whereHas('session', fn($q) => $q->where('course_id', $enrollment->course_id))`. No pagination — bounded by enrollment count.

**Response shape:**

```json
[
  {
    "course_code": "CS-101",
    "course_name": "Introduction to Programming",
    "attended": 8,
    "total": 10,
    "percentage": 80.0,
    "minimum_pct": "75.00",
    "below_minimum": false
  }
]
```

---

## 5. Error Handling

- Unauthenticated → 401 (Sanctum middleware)
- Authenticated user with no student profile → 403 (`abort_unless`)
- Both endpoints return empty collections (not 404) when the student has no records or no enrollments

---

## 6. Tests (`tests/Feature/Api/AttendanceHistoryTest.php`)

Uses `actingAs($user, 'sanctum')` and `LazilyRefreshDatabase`. A helper `historyFixture()` creates a student user with one course enrollment and a closed session.

| Test | Setup | Assertion |
|---|---|---|
| `test_student_can_retrieve_own_attendance_records` | 3 records for student | 200, `data` has 3 items |
| `test_student_cannot_retrieve_another_students_records` | Records belong to a different student | Authenticated student's `data` has 0 items |
| `test_attendance_summary_returns_correct_percentages` | 8 present + 2 absent records for one course | `attended=8`, `total=10`, `percentage=80.0` |
| `test_attendance_summary_flags_courses_below_minimum` | 5 present + 7 absent (41.67%), `min_attendance_pct=75` | `below_minimum=true` |
