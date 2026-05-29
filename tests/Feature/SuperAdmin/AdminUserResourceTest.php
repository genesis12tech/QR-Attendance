<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_admin_users', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUsers = User::factory()->admin()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->assertCanSeeTableRecords($adminUsers);
});

test('super_admin_can_create_admin_user', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(CreateAdminUser::class)
        ->fillForm([
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value,
            'status' => UserStatus::Active->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(User::class, ['email' => 'newadmin@example.com', 'role' => 'admin']);
});

test('super_admin_can_suspend_admin_user', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create();

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('suspend')->table($adminUser))
        ->assertSuccessful();

    expect($adminUser->fresh()->status)->toBe(UserStatus::Suspended);
});

test('suspend_action_writes_audit_log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create();

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('suspend')->table($adminUser));

    expect(
        AuditLog::where('action', 'user.suspended')
            ->where('entity_id', $adminUser->id)
            ->exists()
    )->toBeTrue();
});

test('email_must_be_unique_on_create', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->admin()->create(['email' => 'taken@example.com']);

    $this->actingAs($superAdmin);

    livewire(CreateAdminUser::class)
        ->fillForm([
            'name' => 'Another Admin',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);
});

test('password_confirmation_is_required', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(CreateAdminUser::class)
        ->fillForm([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
            'role' => UserRole::Admin->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

test('super_admin_can_revoke_role_assignment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create();
    $assignment = AdminRoleAssignment::factory()->create([
        'user_id' => $adminUser->id,
        'revoked_at' => null,
    ]);

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('revokeRole')->table($adminUser))
        ->assertSuccessful();

    expect($assignment->fresh()->revoked_at)->not->toBeNull();
});

test('super_admin_can_reinstate_suspended_user', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('reinstate')->table($adminUser))
        ->assertSuccessful();

    expect($adminUser->fresh()->status)->toBe(UserStatus::Active);
});

test('reinstate_action_writes_audit_log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('reinstate')->table($adminUser));

    expect(
        AuditLog::where('action', 'user.reinstated')
            ->where('entity_id', $adminUser->id)
            ->exists()
    )->toBeTrue();
});
