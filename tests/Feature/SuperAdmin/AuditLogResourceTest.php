<?php

use App\Filament\SuperAdmin\Resources\AuditLogs\AuditLogResource;
use App\Filament\SuperAdmin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_audit_logs', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $logs = AuditLog::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->assertCanSeeTableRecords($logs);
});

test('audit_log_has_no_create_action', function () {
    expect(AuditLogResource::canCreate())->toBeFalse();
});

test('audit_log_has_no_edit_action', function () {
    $log = AuditLog::factory()->create();
    expect(AuditLogResource::canEdit($log))->toBeFalse();
});

test('audit_log_has_no_delete_action', function () {
    $log = AuditLog::factory()->create();
    expect(AuditLogResource::canDelete($log))->toBeFalse();
});

test('super_admin_can_view_old_and_new_values_in_modal', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $log = AuditLog::factory()->create([
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
    ]);

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->callAction(TestAction::make('view')->table($log))
        ->assertSuccessful();
});

test('super_admin_can_export_audit_log_csv', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AuditLog::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->callAction('export_csv')
        ->assertSuccessful();
});

test('audit_log_filters_by_action_type', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AuditLog::factory()->create(['action' => 'user.created']);
    AuditLog::factory()->create(['action' => 'session.closed']);

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->filterTable('action', 'user.created')
        ->assertCanSeeTableRecords(AuditLog::where('action', 'user.created')->get())
        ->assertCanNotSeeTableRecords(AuditLog::where('action', 'session.closed')->get());
});

test('audit_log_filters_by_actor_role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AuditLog::factory()->create(['actor_role' => 'admin']);
    AuditLog::factory()->create(['actor_role' => 'faculty']);

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->filterTable('actor_role', 'admin')
        ->assertCanSeeTableRecords(AuditLog::where('actor_role', 'admin')->get())
        ->assertCanNotSeeTableRecords(AuditLog::where('actor_role', 'faculty')->get());
});
