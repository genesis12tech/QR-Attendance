<?php

use App\Enums\AdminAssignmentRole;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\CreateAdminRoleAssignment;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\ListAdminRoleAssignments;
use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_role_assignments', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $assignments = AdminRoleAssignment::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListAdminRoleAssignments::class)
        ->assertCanSeeTableRecords($assignments);
});

test('super_admin_can_create_role_assignment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->admin()->create();
    $department = Department::factory()->create();

    $this->actingAs($superAdmin);

    livewire(CreateAdminRoleAssignment::class)
        ->fillForm([
            'user_id' => $user->id,
            'assigned_by' => $superAdmin->id,
            'role' => AdminAssignmentRole::Admin->value,
            'department_id' => $department->id,
            'assigned_at' => now()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(AdminRoleAssignment::class, [
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);
});

test('super_admin_can_revoke_role_assignment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $assignment = AdminRoleAssignment::factory()->create(['revoked_at' => null]);

    $this->actingAs($superAdmin);

    livewire(ListAdminRoleAssignments::class)
        ->callAction(TestAction::make('revoke')->table($assignment))
        ->assertSuccessful();

    expect($assignment->fresh()->revoked_at)->not->toBeNull();
});

test('revoke_writes_audit_log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $assignment = AdminRoleAssignment::factory()->create(['revoked_at' => null]);

    $this->actingAs($superAdmin);

    livewire(ListAdminRoleAssignments::class)
        ->callAction(TestAction::make('revoke')->table($assignment));

    expect(
        AuditLog::where('action', 'role_assignment.revoked')
            ->where('entity_id', $assignment->id)
            ->exists()
    )->toBeTrue();
});
