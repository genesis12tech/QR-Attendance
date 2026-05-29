<?php

use App\Enums\AttendanceStatus;
use App\Filament\Admin\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Models\AdminRoleAssignment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForAttendanceRecord(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

function recordForDepartment(Department $department): AttendanceRecord
{
    $course = Course::factory()->create(['department_id' => $department->id]);
    $session = AttendanceSession::factory()->active()->create(['course_id' => $course->id]);

    return AttendanceRecord::factory()->create(['session_id' => $session->id]);
}

test('admin_can_list_attendance_records', function () {
    $dept = Department::factory()->create();
    $admin = adminForAttendanceRecord($dept);
    $records = collect([
        recordForDepartment($dept),
        recordForDepartment($dept),
    ]);

    $this->actingAs($admin);

    livewire(ListAttendanceRecords::class)
        ->assertCanSeeTableRecords($records);
});

test('admin_cannot_see_records_from_other_departments', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $admin = adminForAttendanceRecord($dept);

    $ownRecords = collect([recordForDepartment($dept)]);
    $otherRecords = collect([recordForDepartment($otherDept)]);

    $this->actingAs($admin);

    livewire(ListAttendanceRecords::class)
        ->assertCanSeeTableRecords($ownRecords)
        ->assertCanNotSeeTableRecords($otherRecords);
});

test('override_action_updates_status', function () {
    $dept = Department::factory()->create();
    $admin = adminForAttendanceRecord($dept);
    $record = recordForDepartment($dept);

    $this->actingAs($admin);

    livewire(ListAttendanceRecords::class)
        ->callAction(TestAction::make('override')->table($record), [
            'status' => AttendanceStatus::Present->value,
            'override_reason' => 'Student was present, system error caused absence mark.',
        ])
        ->assertNotified();

    assertDatabaseHas(AttendanceRecord::class, [
        'id' => $record->id,
        'status' => AttendanceStatus::Present->value,
        'override_reason' => 'Student was present, system error caused absence mark.',
    ]);
});

test('override_reason_must_be_at_least_20_characters', function () {
    $dept = Department::factory()->create();
    $admin = adminForAttendanceRecord($dept);
    $record = recordForDepartment($dept);

    $this->actingAs($admin);

    livewire(ListAttendanceRecords::class)
        ->callAction(TestAction::make('override')->table($record), [
            'status' => AttendanceStatus::Present->value,
            'override_reason' => 'Too short.',
        ])
        ->assertHasActionErrors(['override_reason']);
});

test('override_action_writes_audit_log_with_old_and_new_values', function () {
    $dept = Department::factory()->create();
    $admin = adminForAttendanceRecord($dept);
    $record = recordForDepartment($dept);

    $this->actingAs($admin);

    livewire(ListAttendanceRecords::class)
        ->callAction(TestAction::make('override')->table($record), [
            'status' => AttendanceStatus::Present->value,
            'override_reason' => 'Student was present, system error occurred during scan.',
        ]);

    assertDatabaseHas(AuditLog::class, [
        'action' => 'attendance_record.overridden',
        'entity_type' => AttendanceRecord::class,
        'entity_id' => $record->id,
        'actor_id' => $admin->id,
    ]);
});

test('override_sets_override_by_to_current_user', function () {
    $dept = Department::factory()->create();
    $admin = adminForAttendanceRecord($dept);
    $record = recordForDepartment($dept);

    $this->actingAs($admin);

    livewire(ListAttendanceRecords::class)
        ->callAction(TestAction::make('override')->table($record), [
            'status' => AttendanceStatus::Present->value,
            'override_reason' => 'Student was present, confirmed by manual check.',
        ]);

    assertDatabaseHas(AttendanceRecord::class, [
        'id' => $record->id,
        'override_by' => $admin->id,
    ]);
});

test('high_risk_filter_returns_records_with_risk_score_gte_50', function () {
    $dept = Department::factory()->create();
    $admin = adminForAttendanceRecord($dept);

    $course = Course::factory()->create(['department_id' => $dept->id]);
    $session = AttendanceSession::factory()->active()->create(['course_id' => $course->id]);

    $highRisk = AttendanceRecord::factory()->create([
        'session_id' => $session->id,
        'risk_score' => 75,
    ]);
    $lowRisk = AttendanceRecord::factory()->create([
        'session_id' => $session->id,
        'risk_score' => 20,
    ]);

    $this->actingAs($admin);

    livewire(ListAttendanceRecords::class)
        ->filterTable('high_risk')
        ->assertCanSeeTableRecords([$highRisk])
        ->assertCanNotSeeTableRecords([$lowRisk]);
});
