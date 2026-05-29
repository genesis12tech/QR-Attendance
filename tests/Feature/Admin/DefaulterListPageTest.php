<?php

use App\Filament\Admin\Pages\DefaulterListPage;
use App\Jobs\SendAbsenceNotifications;
use App\Models\AdminRoleAssignment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForDefaulters(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

/**
 * Creates $sessionCount closed sessions for the given course, then creates
 * $attendedCount present records for the given student+enrollment.
 * Returns the created enrollment.
 */
function createStudentWithAttendance(
    Student $student,
    Course $course,
    int $sessionCount,
    int $attendedCount
): Enrollment {
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    $faculty = Faculty::factory()->create();
    $sessions = AttendanceSession::factory()->count($sessionCount)->closed()->create([
        'course_id' => $course->id,
        'faculty_id' => $faculty->id,
    ]);

    foreach ($sessions->take($attendedCount) as $session) {
        AttendanceRecord::factory()->present()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    return $enrollment;
}

test('admin_can_view_defaulter_list', function () {
    $dept = Department::factory()->create();
    $admin = adminForDefaulters($dept);

    $this->actingAs($admin)
        ->get('/admin/defaulters')
        ->assertSuccessful();
});

test('defaulter_list_only_shows_students_below_minimum_attendance', function () {
    $dept = Department::factory()->create();
    $admin = adminForDefaulters($dept);

    // min_attendance_pct = 75%; strictly below means < 75
    $course = Course::factory()->create([
        'department_id' => $dept->id,
        'min_attendance_pct' => 75.00,
    ]);

    // Shared sessions — both students are measured against the same 4 closed sessions.
    // Using createStudentWithAttendance twice would create 8 sessions total (4 per call),
    // making total_sessions=8 for both and skewing the percentages.
    $faculty = Faculty::factory()->create();
    $sessions = AttendanceSession::factory()->count(4)->closed()->create([
        'course_id' => $course->id,
        'faculty_id' => $faculty->id,
    ]);

    // Student A: 3/4 = 75% — exactly at the minimum, NOT a defaulter
    $studentA = Student::factory()->create(['department_id' => $dept->id]);
    $enrollmentA = Enrollment::factory()->create(['student_id' => $studentA->id, 'course_id' => $course->id]);
    foreach ($sessions->take(3) as $session) {
        AttendanceRecord::factory()->present()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $studentA->id,
            'enrollment_id' => $enrollmentA->id,
        ]);
    }

    // Student B: 2/4 = 50% < 75% — IS a defaulter
    $studentB = Student::factory()->create(['department_id' => $dept->id]);
    $enrollmentB = Enrollment::factory()->create(['student_id' => $studentB->id, 'course_id' => $course->id]);
    foreach ($sessions->take(2) as $session) {
        AttendanceRecord::factory()->present()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $studentB->id,
            'enrollment_id' => $enrollmentB->id,
        ]);
    }

    $this->actingAs($admin);

    livewire(DefaulterListPage::class)
        ->assertCanSeeTableRecords([$enrollmentB])
        ->assertCanNotSeeTableRecords([$enrollmentA]);
});

test('notify_action_dispatches_absence_notifications_job', function () {
    Queue::fake();

    $dept = Department::factory()->create();
    $admin = adminForDefaulters($dept);

    $course = Course::factory()->create([
        'department_id' => $dept->id,
        'min_attendance_pct' => 75.00,
    ]);

    // 1/4 sessions attended = 25%, well below 75%
    $student = Student::factory()->create(['department_id' => $dept->id]);
    createStudentWithAttendance($student, $course, sessionCount: 4, attendedCount: 1);

    $this->actingAs($admin);

    livewire(DefaulterListPage::class)
        ->callAction('notify')
        ->assertNotified();

    Queue::assertPushed(SendAbsenceNotifications::class, function (SendAbsenceNotifications $job) use ($student, $course) {
        return in_array($student->id, $job->studentIds, true)
            && $job->courseId === $course->id;
    });

    Queue::assertPushedTimes(SendAbsenceNotifications::class, 1);
});
