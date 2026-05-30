<?php

use App\Filament\Admin\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Models\AdminRoleAssignment;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForSession(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

function sessionForDepartment(Department $department): AttendanceSession
{
    $course = Course::factory()->create(['department_id' => $department->id]);

    return AttendanceSession::factory()->create(['course_id' => $course->id]);
}

test('admin_can_list_sessions_for_own_department', function () {
    $dept = Department::factory()->create();
    $admin = adminForSession($dept);
    $sessions = collect([
        sessionForDepartment($dept),
        sessionForDepartment($dept),
    ]);

    $this->actingAs($admin);

    livewire(ListAttendanceSessions::class)
        ->assertCanSeeTableRecords($sessions);
});

test('admin_cannot_see_sessions_from_other_departments', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $admin = adminForSession($dept);

    $ownSession = sessionForDepartment($dept);
    $otherSession = sessionForDepartment($otherDept);

    $this->actingAs($admin);

    livewire(ListAttendanceSessions::class)
        ->assertCanSeeTableRecords([$ownSession])
        ->assertCanNotSeeTableRecords([$otherSession]);
});

test('admin_cannot_create_sessions', function () {
    $dept = Department::factory()->create();
    $admin = adminForSession($dept);

    $this->actingAs($admin);

    livewire(ListAttendanceSessions::class)
        ->assertActionDoesNotExist('create');
});
