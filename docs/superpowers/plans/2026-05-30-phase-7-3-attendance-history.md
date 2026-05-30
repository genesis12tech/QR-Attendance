# Phase 7.3 — Attendance History Endpoint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two read-only GET endpoints so students can retrieve their own attendance records and a per-course summary from the mobile app.

**Architecture:** Single thin `AttendanceController` with `index()` (paginated record list) and `summary()` (per-course attended/total/percentage), both under the existing `auth:sanctum, throttle:30,1` middleware group. No service class needed — queries are simple enough to live inline.

**Tech Stack:** Laravel 12, Sanctum, Pest 3, Eloquent (AttendanceRecord, Enrollment, Course).

**PHP binary:** `/Users/thomas/.config/herd-lite/bin/php`

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Modify | `routes/api.php` | Add 2 GET routes to the existing throttle:30,1 group |
| Create | `app/Http/Controllers/Api/V1/Student/AttendanceController.php` | `index()` + `summary()` |
| Create | `tests/Feature/Api/AttendanceHistoryTest.php` | 4 feature tests |

---

## Task 1: Test file + routes

**Files:**
- Create: `tests/Feature/Api/AttendanceHistoryTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Scaffold the test file**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest "Api/AttendanceHistoryTest" --no-interaction
```

- [ ] **Step 2: Replace the generated file with the complete test suite**

Write the following to `tests/Feature/Api/AttendanceHistoryTest.php`:

```php
<?php

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

/**
 * Standard fixture: student with one active enrollment in a closed session.
 */
function historyFixture(): array
{
    $department = Department::factory()->create();
    $course = Course::factory()->create([
        'department_id'      => $department->id,
        'min_attendance_pct' => 75.00,
    ]);
    $classGroup = ClassGroup::factory()->create(['course_id' => $course->id]);
    $user = User::factory()->student()->create();
    $student = Student::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
    $session = AttendanceSession::factory()->closed()->create([
        'course_id'      => $course->id,
        'class_group_id' => $classGroup->id,
    ]);
    Enrollment::factory()->create([
        'student_id'     => $student->id,
        'course_id'      => $course->id,
        'class_group_id' => $classGroup->id,
        'status'         => EnrollmentStatus::Active,
    ]);

    return compact('user', 'student', 'session', 'course');
}

// ── index ────────────────────────────────────────────────────────────────────

it('returns own attendance records paginated', function () {
    $f = historyFixture();
    AttendanceRecord::factory()->count(3)->create([
        'student_id' => $f['student']->id,
        'session_id' => $f['session']->id,
    ]);

    $this->actingAs($f['user'], 'sanctum')
        ->getJson('/api/v1/student/attendance')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data'  => [['id', 'status', 'marked_at', 'risk_score', 'session']],
            'meta'  => ['current_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
});

it('does not return another students records', function () {
    $f = historyFixture();
    $otherStudent = Student::factory()->create();
    AttendanceRecord::factory()->count(2)->create([
        'student_id' => $otherStudent->id,
        'session_id' => $f['session']->id,
    ]);

    $this->actingAs($f['user'], 'sanctum')
        ->getJson('/api/v1/student/attendance')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ── summary ──────────────────────────────────────────────────────────────────

it('returns correct attendance counts in summary', function () {
    $f = historyFixture();
    AttendanceRecord::factory()->count(8)->create([
        'student_id' => $f['student']->id,
        'session_id' => $f['session']->id,
        'status'     => AttendanceStatus::Present,
    ]);
    AttendanceRecord::factory()->count(2)->create([
        'student_id' => $f['student']->id,
        'session_id' => $f['session']->id,
        'status'     => AttendanceStatus::Absent,
    ]);

    $this->actingAs($f['user'], 'sanctum')
        ->getJson('/api/v1/student/attendance/summary')
        ->assertOk()
        ->assertJsonFragment([
            'course_code'   => $f['course']->code,
            'attended'      => 8,
            'total'         => 10,
            'below_minimum' => false,
        ]);
});

it('flags courses below the minimum attendance threshold', function () {
    $f = historyFixture();
    // 5 present / 12 total = 41.67% — below 75% minimum
    AttendanceRecord::factory()->count(5)->create([
        'student_id' => $f['student']->id,
        'session_id' => $f['session']->id,
        'status'     => AttendanceStatus::Present,
    ]);
    AttendanceRecord::factory()->count(7)->create([
        'student_id' => $f['student']->id,
        'session_id' => $f['session']->id,
        'status'     => AttendanceStatus::Absent,
    ]);

    $this->actingAs($f['user'], 'sanctum')
        ->getJson('/api/v1/student/attendance/summary')
        ->assertOk()
        ->assertJsonFragment([
            'below_minimum' => true,
            'attended'      => 5,
            'total'         => 12,
        ]);
});
```

- [ ] **Step 3: Add the two routes to `routes/api.php`**

Open `routes/api.php`. The current file looks like:

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

    Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('attendance/scan', [ScanController::class, 'store']);
    });
});
```

Replace it with:

```php
<?php

use App\Http\Controllers\Api\V1\Student\AttendanceController;
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
        Route::get('attendance', [AttendanceController::class, 'index']);
        Route::get('attendance/summary', [AttendanceController::class, 'summary']);
    });

    Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('attendance/scan', [ScanController::class, 'store']);
    });
});
```

- [ ] **Step 4: Verify the routes are registered (will fail — controller doesn't exist yet)**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan route:list --path=v1/student/attendance --no-interaction
```

Expected: two GET routes listed for `v1/student/attendance` and `v1/student/attendance/summary`, both pointing at `AttendanceController`.

---

## Task 2: AttendanceController

**Files:**
- Create: `app/Http/Controllers/Api/V1/Student/AttendanceController.php`

- [ ] **Step 1: Create the controller**

Write the following to `app/Http/Controllers/Api/V1/Student/AttendanceController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        abort_unless($student !== null, 403, 'No student profile associated with this account.');

        $records = AttendanceRecord::where('student_id', $student->id)
            ->with(['session.course'])
            ->orderByDesc('marked_at')
            ->paginate(15);

        return response()->json($records->through(fn ($record) => [
            'id'         => $record->id,
            'status'     => $record->status->value,
            'marked_at'  => $record->marked_at?->toISOString(),
            'risk_score' => $record->risk_score,
            'session'    => [
                'started_at'  => $record->session?->started_at?->toISOString(),
                'course_code' => $record->session?->course?->code,
                'course_name' => $record->session?->course?->name,
            ],
        ]));
    }

    public function summary(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        abort_unless($student !== null, 403, 'No student profile associated with this account.');

        $enrollments = Enrollment::where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active)
            ->with('course')
            ->get();

        $summary = $enrollments->map(function (Enrollment $enrollment) use ($student) {
            $total = AttendanceRecord::where('student_id', $student->id)
                ->whereHas('session', fn ($q) => $q->where('course_id', $enrollment->course_id))
                ->count();

            $attended = AttendanceRecord::where('student_id', $student->id)
                ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
                ->whereHas('session', fn ($q) => $q->where('course_id', $enrollment->course_id))
                ->count();

            $percentage = $total > 0 ? round($attended / $total * 100, 2) : 0.0;
            $minimumPct = (float) $enrollment->course->min_attendance_pct;

            return [
                'course_code'   => $enrollment->course->code,
                'course_name'   => $enrollment->course->name,
                'attended'      => $attended,
                'total'         => $total,
                'percentage'    => $percentage,
                'minimum_pct'   => $enrollment->course->min_attendance_pct,
                'below_minimum' => $percentage < $minimumPct,
            ];
        });

        return response()->json($summary);
    }
}
```

- [ ] **Step 2: Run the full AttendanceHistoryTest suite**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=AttendanceHistoryTest
```

Expected: all 4 tests pass. Common failure causes:

- `historyFixture()` conflict: if the helper name clashes with another test file's `historyFixture()`, rename it to `attendanceHistoryFixture()` in both the test and usages within the file.
- `assertJsonCount(0, 'data')` fails because `AttendanceRecord::factory()->count(2)->create([...])` is also creating its own session/student via factory defaults — pass explicit `session_id` and `student_id` to override all FKs.
- `below_minimum` is `false` when expected `true`: verify `min_attendance_pct = 75.00` on the course and that 5/12 = 41.67% < 75%.

---

## Task 3: Format + full test run + commit

**Files:**
- All PHP files modified in this feature

- [ ] **Step 1: Run Pint**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
```

Expected: no errors. Formatting applied automatically.

- [ ] **Step 2: Run the full test suite**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
```

Expected: all tests pass. If `historyFixture()` conflicts with another helper defined in a different test file (Pest loads all helpers globally), rename the function to `attendanceHistoryFixture()` and update the four call sites within `AttendanceHistoryTest.php`.

- [ ] **Step 3: Commit**

```bash
git add routes/api.php \
        app/Http/Controllers/Api/V1/Student/AttendanceController.php \
        tests/Feature/Api/AttendanceHistoryTest.php

git commit -m "$(cat <<'EOF'
feat: implement Phase 7.3 — attendance history endpoints

GET /api/v1/student/attendance returns paginated own records.
GET /api/v1/student/attendance/summary returns per-course attended/
total/percentage with below_minimum flag vs course.min_attendance_pct.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```
