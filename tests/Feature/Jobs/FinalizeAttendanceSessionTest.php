<?php

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Jobs\FinalizeAttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

function makeActiveSession(): AttendanceSession
{
    $course = Course::factory()->create();
    $classGroup = ClassGroup::factory()->create(['course_id' => $course->id]);

    return AttendanceSession::factory()->active()->create([
        'course_id' => $course->id,
        'class_group_id' => $classGroup->id,
    ]);
}

it('sets session status to closed', function () {
    $session = makeActiveSession();

    (new FinalizeAttendanceSession($session))->handle();

    expect($session->fresh()->status)->toBe(SessionStatus::Closed)
        ->and($session->fresh()->closed_at)->not->toBeNull();
});

it('marks enrolled students with no attendance record as absent', function () {
    $session = makeActiveSession();

    $enrolledStudent = Student::factory()->create();
    Enrollment::factory()->create([
        'student_id' => $enrolledStudent->id,
        'course_id' => $session->course_id,
        'class_group_id' => $session->class_group_id,
    ]);

    (new FinalizeAttendanceSession($session))->handle();

    expect(
        AttendanceRecord::where('session_id', $session->id)
            ->where('student_id', $enrolledStudent->id)
            ->where('status', AttendanceStatus::Absent)
            ->exists()
    )->toBeTrue();
});

it('does not create a duplicate absent record when a record already exists', function () {
    $session = makeActiveSession();

    $student = Student::factory()->create();
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $session->course_id,
        'class_group_id' => $session->class_group_id,
    ]);
    AttendanceRecord::factory()->create([
        'session_id' => $session->id,
        'student_id' => $student->id,
        'status' => AttendanceStatus::Present,
    ]);

    (new FinalizeAttendanceSession($session))->handle();

    expect(
        AttendanceRecord::where('session_id', $session->id)
            ->where('student_id', $student->id)
            ->count()
    )->toBe(1);
});

it('updates session totals correctly', function () {
    $session = makeActiveSession();

    $students = Student::factory()->count(5)->create();

    foreach ($students as $student) {
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $session->course_id,
            'class_group_id' => $session->class_group_id,
        ]);
    }

    AttendanceRecord::factory()->create([
        'session_id' => $session->id,
        'student_id' => $students[0]->id,
        'status' => AttendanceStatus::Present,
    ]);
    AttendanceRecord::factory()->create([
        'session_id' => $session->id,
        'student_id' => $students[1]->id,
        'status' => AttendanceStatus::Present,
    ]);
    AttendanceRecord::factory()->create([
        'session_id' => $session->id,
        'student_id' => $students[2]->id,
        'status' => AttendanceStatus::Late,
    ]);
    // students[3] and students[4] have no record → will be marked absent

    (new FinalizeAttendanceSession($session))->handle();

    $session->refresh();

    expect($session->total_present)->toBe(2)
        ->and($session->total_late)->toBe(1)
        ->and($session->total_absent)->toBe(2);
});

it('writes an audit log entry on session close', function () {
    $session = makeActiveSession();

    (new FinalizeAttendanceSession($session))->handle();

    expect(
        AuditLog::where('action', 'session.closed')
            ->where('entity_type', AttendanceSession::class)
            ->where('entity_id', $session->id)
            ->exists()
    )->toBeTrue();
});
