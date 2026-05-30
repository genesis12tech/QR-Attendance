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
 * Standard fixture: student with one active enrollment and course context.
 */
function historyFixture(): array
{
    $department = Department::factory()->create();
    $course = Course::factory()->create([
        'department_id' => $department->id,
        'min_attendance_pct' => 75.00,
    ]);
    $classGroup = ClassGroup::factory()->create(['course_id' => $course->id]);
    $user = User::factory()->student()->create();
    $student = Student::factory()->create(['user_id' => $user->id, 'department_id' => $department->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'class_group_id' => $classGroup->id,
        'status' => EnrollmentStatus::Active,
    ]);

    return compact('user', 'student', 'course', 'classGroup');
}

/**
 * Create N closed sessions for the given course/classGroup, each with one
 * attendance record for the given student. Returns the created records.
 *
 * @param  array<string, mixed>  $recordOverrides  Extra attributes merged onto every record.
 */
function createRecordsAcrossSessions(array $fixture, int $count, array $recordOverrides = []): void
{
    $sessions = AttendanceSession::factory()->count($count)->closed()->create([
        'course_id' => $fixture['course']->id,
        'class_group_id' => $fixture['classGroup']->id,
    ]);

    foreach ($sessions as $session) {
        AttendanceRecord::factory()->create(array_merge([
            'student_id' => $fixture['student']->id,
            'session_id' => $session->id,
        ], $recordOverrides));
    }
}

// ── index ────────────────────────────────────────────────────────────────────

it('returns own attendance records paginated', function () {
    $f = historyFixture();
    createRecordsAcrossSessions($f, 3);

    $this->actingAs($f['user'], 'sanctum')
        ->getJson('/api/v1/student/attendance')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [['id', 'status', 'marked_at', 'risk_score', 'session']],
            'meta' => ['current_page', 'per_page', 'total'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
});

it('does not return another students records', function () {
    $f = historyFixture();
    $otherStudent = Student::factory()->create();
    $sessions = AttendanceSession::factory()->count(2)->closed()->create([
        'course_id' => $f['course']->id,
        'class_group_id' => $f['classGroup']->id,
    ]);
    foreach ($sessions as $session) {
        AttendanceRecord::factory()->create([
            'student_id' => $otherStudent->id,
            'session_id' => $session->id,
        ]);
    }

    $this->actingAs($f['user'], 'sanctum')
        ->getJson('/api/v1/student/attendance')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ── summary ──────────────────────────────────────────────────────────────────

it('returns correct attendance counts in summary', function () {
    $f = historyFixture();
    createRecordsAcrossSessions($f, 8, ['status' => AttendanceStatus::Present]);
    createRecordsAcrossSessions($f, 2, ['status' => AttendanceStatus::Absent]);

    $this->actingAs($f['user'], 'sanctum')
        ->getJson('/api/v1/student/attendance/summary')
        ->assertOk()
        ->assertJsonFragment([
            'course_code' => $f['course']->code,
            'attended' => 8,
            'total' => 10,
            'below_minimum' => false,
        ]);
});

it('flags courses below the minimum attendance threshold', function () {
    $f = historyFixture();
    // 5 present / 12 total = 41.67% — below 75% minimum
    createRecordsAcrossSessions($f, 5, ['status' => AttendanceStatus::Present]);
    createRecordsAcrossSessions($f, 7, ['status' => AttendanceStatus::Absent]);

    $this->actingAs($f['user'], 'sanctum')
        ->getJson('/api/v1/student/attendance/summary')
        ->assertOk()
        ->assertJsonFragment([
            'below_minimum' => true,
            'attended' => 5,
            'total' => 12,
        ]);
});
