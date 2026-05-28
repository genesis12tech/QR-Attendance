# Phase 1.7 — DemoDataSeeder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create `DemoDataSeeder` with a deterministic fixture set for local/staging development, then wire it into `DatabaseSeeder` behind an `App::isLocal()` guard.

**Architecture:** `DemoDataSeeder` calls `DefaultSettingsSeeder` first (making it the single entry point for demo data), then manually creates a specific fixture set using model `create()` calls (not factories) so the fixture data is fully deterministic — fixed emails, names, roll numbers. `DatabaseSeeder` calls `DemoDataSeeder` only when `app()->isLocal()` is true.

**Tech Stack:** Laravel 12, Eloquent models, PHP Enums (`app/Enums/`), all factories and models from Phases 1.4–1.6.

---

## Files

- **Create:** `database/seeders/DemoDataSeeder.php`
- **Modify:** `database/seeders/DatabaseSeeder.php` — replace placeholder test-user creation with an `app()->isLocal()` guard that calls `DemoDataSeeder`

---

### Task 1: Create DemoDataSeeder — Users, Departments, Faculty

**Files:**
- Create: `database/seeders/DemoDataSeeder.php`

- [ ] **Step 1: Create the file via Artisan**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:seeder DemoDataSeeder --no-interaction
```

Expected output: `INFO  Seeder [database/seeders/DemoDataSeeder.php] created successfully.`

- [ ] **Step 2: Write the seeder — part 1 (super admin, departments, faculty)**

Replace the entire contents of `database/seeders/DemoDataSeeder.php` with:

```php
<?php

namespace Database\Seeders;

use App\Enums\AdminAssignmentRole;
use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\EnrollmentStatus;
use App\Enums\FacultyStatus;
use App\Enums\ProxySeverity;
use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AdminRoleAssignment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\ProxyFlag;
use App\Models\Room;
use App\Models\Student;
use App\Models\Timetable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DefaultSettingsSeeder::class);

        // ── Super Admin ──────────────────────────────────────────────────────
        $superAdmin = User::factory()->superAdmin()->create([
            'name'     => 'Super Admin',
            'email'    => 'superadmin@demo.test',
            'password' => Hash::make('password'),
        ]);

        // ── Departments ──────────────────────────────────────────────────────
        $csDept = Department::create([
            'name'            => 'Computer Science',
            'code'            => 'CS',
            'head_faculty_id' => null,
            'is_active'       => true,
        ]);

        $mathDept = Department::create([
            'name'            => 'Mathematics',
            'code'            => 'MATH',
            'head_faculty_id' => null,
            'is_active'       => true,
        ]);

        // ── Faculty (3 total: 2 CS, 1 MATH) ─────────────────────────────────
        $faculty1User = User::factory()->faculty()->create([
            'name'     => 'Dr. Alice Johnson',
            'email'    => 'faculty1@demo.test',
            'password' => Hash::make('password'),
        ]);
        $faculty1 = Faculty::create([
            'user_id'       => $faculty1User->id,
            'department_id' => $csDept->id,
            'employee_code' => 'EMP-0001',
            'designation'   => 'Associate Professor',
            'status'        => FacultyStatus::Active,
        ]);

        $faculty2User = User::factory()->faculty()->create([
            'name'     => 'Dr. Bob Smith',
            'email'    => 'faculty2@demo.test',
            'password' => Hash::make('password'),
        ]);
        $faculty2 = Faculty::create([
            'user_id'       => $faculty2User->id,
            'department_id' => $csDept->id,
            'employee_code' => 'EMP-0002',
            'designation'   => 'Senior Lecturer',
            'status'        => FacultyStatus::Active,
        ]);

        $faculty3User = User::factory()->faculty()->create([
            'name'     => 'Dr. Carol White',
            'email'    => 'faculty3@demo.test',
            'password' => Hash::make('password'),
        ]);
        $faculty3 = Faculty::create([
            'user_id'       => $faculty3User->id,
            'department_id' => $mathDept->id,
            'employee_code' => 'EMP-0003',
            'designation'   => 'Lecturer',
            'status'        => FacultyStatus::Active,
        ]);

        // Set faculty1 as CS department head
        $csDept->update(['head_faculty_id' => $faculty1->id]);

        // ── Courses and Class Groups ─────────────────────────────────────────
        $csCourse1  = Course::create(['department_id' => $csDept->id,   'code' => 'CS-101',   'name' => 'Data Structures',  'semester' => 3, 'credits' => 4, 'min_attendance_pct' => 75.00]);
        $csGroup1A  = ClassGroup::create(['course_id' => $csCourse1->id, 'name' => 'Group A']);
        $csGroup1B  = ClassGroup::create(['course_id' => $csCourse1->id, 'name' => 'Group B']);

        $csCourse2  = Course::create(['department_id' => $csDept->id,   'code' => 'CS-202',   'name' => 'Operating Systems', 'semester' => 5, 'credits' => 3, 'min_attendance_pct' => 75.00]);
        $csGroup2A  = ClassGroup::create(['course_id' => $csCourse2->id, 'name' => 'Group A']);
        $csGroup2B  = ClassGroup::create(['course_id' => $csCourse2->id, 'name' => 'Group B']);

        $mathCourse = Course::create(['department_id' => $mathDept->id, 'code' => 'MATH-101', 'name' => 'Calculus I',        'semester' => 1, 'credits' => 3, 'min_attendance_pct' => 75.00]);
        $mathGroup1A = ClassGroup::create(['course_id' => $mathCourse->id, 'name' => 'Group A']);
        $mathGroup1B = ClassGroup::create(['course_id' => $mathCourse->id, 'name' => 'Group B']);

        // ── Room ─────────────────────────────────────────────────────────────
        $room = Room::create([
            'name'      => 'Lab 101',
            'building'  => 'Science Block',
            'capacity'  => 40,
            'is_active' => true,
        ]);

        // ── Timetables for CS courses (Mon–Fri, 1 week) ──────────────────────
        // CS-101 Group A: Mon/Wed/Fri with faculty1
        // CS-101 Group B: Tue/Thu with faculty2
        // CS-202 Group A: Tue/Thu with faculty1
        // CS-202 Group B: Mon/Wed/Fri with faculty2
        $timetableEntries = [
            [DayOfWeek::Monday,    '09:00:00', '11:00:00', $csCourse1, $csGroup1A, $faculty1],
            [DayOfWeek::Wednesday, '09:00:00', '11:00:00', $csCourse1, $csGroup1A, $faculty1],
            [DayOfWeek::Friday,    '09:00:00', '11:00:00', $csCourse1, $csGroup1A, $faculty1],
            [DayOfWeek::Tuesday,   '11:00:00', '13:00:00', $csCourse1, $csGroup1B, $faculty2],
            [DayOfWeek::Thursday,  '11:00:00', '13:00:00', $csCourse1, $csGroup1B, $faculty2],
            [DayOfWeek::Tuesday,   '09:00:00', '11:00:00', $csCourse2, $csGroup2A, $faculty1],
            [DayOfWeek::Thursday,  '09:00:00', '11:00:00', $csCourse2, $csGroup2A, $faculty1],
            [DayOfWeek::Monday,    '11:00:00', '13:00:00', $csCourse2, $csGroup2B, $faculty2],
            [DayOfWeek::Wednesday, '11:00:00', '13:00:00', $csCourse2, $csGroup2B, $faculty2],
            [DayOfWeek::Friday,    '11:00:00', '13:00:00', $csCourse2, $csGroup2B, $faculty2],
        ];

        $effectiveFrom = now()->startOfMonth()->toDateString();

        foreach ($timetableEntries as [$day, $start, $end, $course, $group, $faculty]) {
            Timetable::create([
                'course_id'       => $course->id,
                'class_group_id'  => $group->id,
                'faculty_id'      => $faculty->id,
                'room_id'         => $room->id,
                'day_of_week'     => $day->value,
                'start_time'      => $start,
                'end_time'        => $end,
                'effective_from'  => $effectiveFrom,
                'effective_until' => null,
            ]);
        }

        // ── Students (15 CS + 5 MATH = 20 total) ────────────────────────────
        $csStudents = collect();
        for ($i = 1; $i <= 15; $i++) {
            $user = User::factory()->student()->create([
                'name'     => "CS Student {$i}",
                'email'    => "csstudent{$i}@demo.test",
                'password' => Hash::make('password'),
            ]);
            $csStudents->push(Student::create([
                'user_id'       => $user->id,
                'department_id' => $csDept->id,
                'roll_no'       => '2024' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'batch_year'    => 2024,
                'section'       => $i <= 8 ? 'A' : 'B',
                'status'        => StudentStatus::Active,
            ]));
        }

        $mathStudents = collect();
        for ($i = 1; $i <= 5; $i++) {
            $user = User::factory()->student()->create([
                'name'     => "MATH Student {$i}",
                'email'    => "mathstudent{$i}@demo.test",
                'password' => Hash::make('password'),
            ]);
            $mathStudents->push(Student::create([
                'user_id'       => $user->id,
                'department_id' => $mathDept->id,
                'roll_no'       => '2024' . str_pad(100 + $i, 3, '0', STR_PAD_LEFT),
                'batch_year'    => 2024,
                'section'       => 'A',
                'status'        => StudentStatus::Active,
            ]));
        }

        // Enroll all 15 CS students in CS-101 Group A (this is the session we will close)
        $enrolledAt = now()->subMonth()->toDateString();
        $csStudents->each(fn (Student $s) => Enrollment::create([
            'student_id'     => $s->id,
            'course_id'      => $csCourse1->id,
            'class_group_id' => $csGroup1A->id,
            'status'         => EnrollmentStatus::Active,
            'enrolled_at'    => $enrolledAt,
        ]));

        // Enroll MATH students in MATH-101 Group A
        $mathStudents->each(fn (Student $s) => Enrollment::create([
            'student_id'     => $s->id,
            'course_id'      => $mathCourse->id,
            'class_group_id' => $mathGroup1A->id,
            'status'         => EnrollmentStatus::Active,
            'enrolled_at'    => $enrolledAt,
        ]));

        // ── Admin user + AdminRoleAssignment for CS dept ─────────────────────
        $adminUser = User::factory()->admin()->create([
            'name'     => 'CS Department Admin',
            'email'    => 'admin@demo.test',
            'password' => Hash::make('password'),
        ]);

        AdminRoleAssignment::create([
            'user_id'       => $adminUser->id,
            'assigned_by'   => $superAdmin->id,
            'role'          => AdminAssignmentRole::Admin,
            'department_id' => $csDept->id,
            'assigned_at'   => now()->subWeek(),
            'revoked_at'    => null,
        ]);

        // ── Closed AttendanceSession for CS-101 Group A ──────────────────────
        $sessionStarted = now()->subDay()->setTime(9, 0, 0);
        $sessionClosed  = now()->subDay()->setTime(11, 0, 0);

        $closedSession = AttendanceSession::create([
            'faculty_id'      => $faculty1->id,
            'course_id'       => $csCourse1->id,
            'class_group_id'  => $csGroup1A->id,
            'room_id'         => $room->id,
            'timetable_id'    => null,
            'status'          => SessionStatus::Closed,
            'started_at'      => $sessionStarted,
            'closed_at'       => $sessionClosed,
            'close_reason'    => null,
            'total_enrolled'  => 15,
            'total_present'   => 10,
            'total_late'      => 3,
            'total_absent'    => 0,
        ]);

        // Reload enrollments for CS-101 Group A
        $enrollments = Enrollment::where('course_id', $csCourse1->id)
            ->where('class_group_id', $csGroup1A->id)
            ->get();

        // 10 present attendance records (enrollments 0–9)
        $enrollments->take(10)->each(fn (Enrollment $enrollment) => AttendanceRecord::create([
            'attendance_session_id' => $closedSession->id,
            'student_id'            => $enrollment->student_id,
            'enrollment_id'         => $enrollment->id,
            'status'                => AttendanceStatus::Present,
            'marked_at'             => $sessionStarted->copy()->addMinutes(5),
            'risk_score'            => 0,
        ]));

        // 3 late attendance records (enrollments 10–12)
        $enrollments->slice(10, 3)->each(fn (Enrollment $enrollment) => AttendanceRecord::create([
            'attendance_session_id' => $closedSession->id,
            'student_id'            => $enrollment->student_id,
            'enrollment_id'         => $enrollment->id,
            'status'                => AttendanceStatus::Late,
            'marked_at'             => $sessionStarted->copy()->addMinutes(20),
            'risk_score'            => 10,
        ]));

        // 2 pending_review records (enrollments 13–14)
        $pendingRecords = [];
        $enrollments->slice(13, 2)->each(function (Enrollment $enrollment) use ($closedSession, $sessionStarted, &$pendingRecords) {
            $pendingRecords[] = AttendanceRecord::create([
                'attendance_session_id' => $closedSession->id,
                'student_id'            => $enrollment->student_id,
                'enrollment_id'         => $enrollment->id,
                'status'                => AttendanceStatus::PendingReview,
                'marked_at'             => $sessionStarted->copy()->addMinutes(10),
                'risk_score'            => 65,
                'evidence_json'         => ['reason' => 'gps_mismatch', 'distance_m' => 120],
            ]);
        });

        // 2 ProxyFlag rows for the pending_review records
        foreach ($pendingRecords as $record) {
            ProxyFlag::create([
                'attendance_record_id' => $record->id,
                'severity'             => ProxySeverity::Medium,
                'reason_code'          => 'gps_mismatch',
                'risk_score'           => 65,
                'evidence_json'        => ['distance_m' => 120, 'threshold_m' => 50],
                'review_status'        => ReviewStatus::Pending,
                'reviewer_id'          => null,
                'reviewer_notes'       => null,
                'reviewed_at'          => null,
            ]);
        }

        // ── AuditLog rows for the closed session lifecycle ───────────────────
        AuditLog::create([
            'actor_id'    => $faculty1User->id,
            'actor_role'  => UserRole::Faculty->value,
            'action'      => 'session.started',
            'entity_type' => AttendanceSession::class,
            'entity_id'   => $closedSession->id,
            'old_values'  => ['status' => SessionStatus::Pending->value],
            'new_values'  => ['status' => SessionStatus::Active->value],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'Demo/1.0',
        ]);

        AuditLog::create([
            'actor_id'    => $faculty1User->id,
            'actor_role'  => UserRole::Faculty->value,
            'action'      => 'session.closed',
            'entity_type' => AttendanceSession::class,
            'entity_id'   => $closedSession->id,
            'old_values'  => ['status' => SessionStatus::Active->value],
            'new_values'  => ['status' => SessionStatus::Closed->value, 'total_present' => 10, 'total_late' => 3],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'Demo/1.0',
        ]);

        AuditLog::create([
            'actor_id'    => $superAdmin->id,
            'actor_role'  => UserRole::SuperAdmin->value,
            'action'      => 'admin.assigned',
            'entity_type' => AdminRoleAssignment::class,
            'entity_id'   => $adminUser->id,
            'old_values'  => [],
            'new_values'  => ['role' => AdminAssignmentRole::Admin->value, 'department' => 'CS'],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'Demo/1.0',
        ]);

        AuditLog::create([
            'actor_id'    => $adminUser->id,
            'actor_role'  => UserRole::Admin->value,
            'action'      => 'proxy_flag.flagged',
            'entity_type' => ProxyFlag::class,
            'entity_id'   => $pendingRecords[0]->id,
            'old_values'  => [],
            'new_values'  => ['severity' => ProxySeverity::Medium->value, 'reason_code' => 'gps_mismatch'],
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'Demo/1.0',
        ]);
    }
}
```

- [ ] **Step 3: Run Pint to fix formatting**

```bash
/Users/thomas/Sites/localhost/qr-attendance/vendor/bin/pint --dirty --format agent
```

Expected: any formatting issues corrected.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/DemoDataSeeder.php
git commit -m "feat: add DemoDataSeeder with deterministic fixture set"
```

---

### Task 2: Update DatabaseSeeder

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Replace DatabaseSeeder contents**

Replace the entire contents of `database/seeders/DatabaseSeeder.php` with:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isLocal()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
/Users/thomas/Sites/localhost/qr-attendance/vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "feat: wire DemoDataSeeder into DatabaseSeeder behind isLocal guard"
```

---

### Task 3: Verify Seeder Runs

No automated tests for this phase — verified by running the seeder and checking row counts.

- [ ] **Step 1: Migrate fresh and seed**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan migrate:fresh --seed --no-interaction
```

Expected: migrates all tables successfully and seeds without exceptions.

- [ ] **Step 2: Verify row counts via tinker**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan tinker --execute '
echo "Users: " . App\Models\User::count() . "\n";
echo "Departments: " . App\Models\Department::count() . "\n";
echo "Faculty: " . App\Models\Faculty::count() . "\n";
echo "Courses: " . App\Models\Course::count() . "\n";
echo "ClassGroups: " . App\Models\ClassGroup::count() . "\n";
echo "Timetables: " . App\Models\Timetable::count() . "\n";
echo "Students: " . App\Models\Student::count() . "\n";
echo "Enrollments: " . App\Models\Enrollment::count() . "\n";
echo "Sessions: " . App\Models\AttendanceSession::count() . "\n";
echo "Records: " . App\Models\AttendanceRecord::count() . "\n";
echo "ProxyFlags: " . App\Models\ProxyFlag::count() . "\n";
echo "AuditLogs: " . App\Models\AuditLog::count() . "\n";
echo "AdminAssignments: " . App\Models\AdminRoleAssignment::count() . "\n";
echo "SecurityPolicies: " . App\Models\SecurityPolicy::count() . "\n";
echo "SystemSettings: " . App\Models\SystemSetting::count() . "\n";
'
```

Expected counts:
| Model | Expected |
|---|---|
| User | 21 (1 super admin + 3 faculty + 15 CS students + 1 admin + 1 math... wait, 1 super + 3 faculty + 15 CS students + 5 MATH students + 1 admin = 25) |
| Department | 2 |
| Faculty | 3 |
| Course | 3 |
| ClassGroup | 6 |
| Timetable | 10 |
| Student | 20 |
| Enrollment | 20 (15 CS in CS-101 Group A + 5 MATH in MATH-101 Group A) |
| AttendanceSession | 1 |
| AttendanceRecord | 15 |
| ProxyFlag | 2 |
| AuditLog | 4 |
| AdminRoleAssignment | 1 |
| SecurityPolicy | 1 |
| SystemSetting | 5 |

Correct User count: 1 (super admin) + 3 (faculty users) + 15 (CS students) + 5 (MATH students) + 1 (admin) = **25 Users**

- [ ] **Step 3: Verify CS department has head_faculty set**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan tinker --execute '
$cs = App\Models\Department::where("code", "CS")->first();
echo "CS head_faculty_id: " . $cs->head_faculty_id . "\n";
echo "Head faculty name: " . $cs->headFaculty->user->name . "\n";
'
```

Expected: `CS head_faculty_id: 1` (or similar non-null value), `Head faculty name: Dr. Alice Johnson`

- [ ] **Step 4: Verify closed session totals**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan tinker --execute '
$s = App\Models\AttendanceSession::first();
echo "Status: " . $s->status->value . "\n";
echo "total_enrolled: " . $s->total_enrolled . "\n";
echo "total_present: " . $s->total_present . "\n";
echo "total_late: " . $s->total_late . "\n";
echo "Present records: " . $s->attendanceRecords()->where("status", "present")->count() . "\n";
echo "Late records: " . $s->attendanceRecords()->where("status", "late")->count() . "\n";
echo "PendingReview records: " . $s->attendanceRecords()->where("status", "pending_review")->count() . "\n";
'
```

Expected:
- Status: `closed`
- total_enrolled: `15`
- total_present: `10`
- total_late: `3`
- Present records: `10`
- Late records: `3`
- PendingReview records: `2`

- [ ] **Step 5: Commit if any fixes were needed**

If step 1–4 required any code corrections, commit them:

```bash
git add -p
git commit -m "fix: correct DemoDataSeeder data after verification"
```
