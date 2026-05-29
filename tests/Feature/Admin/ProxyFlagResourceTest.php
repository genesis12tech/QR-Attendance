<?php

use App\Enums\ReviewStatus;
use App\Filament\Admin\Resources\ProxyFlags\Pages\ListProxyFlags;
use App\Filament\Admin\Resources\ProxyFlags\ProxyFlagResource;
use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\ProxyFlag;
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

function adminForProxyFlag(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_list_proxy_flags', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);
    $flags = ProxyFlag::factory()->count(3)->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->assertCanSeeTableRecords($flags);
});

test('admin_can_approve_proxy_flag_with_optional_note', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('approve')->table($flag), [
            'reviewer_notes' => 'Looks legitimate.',
        ])
        ->assertNotified();

    assertDatabaseHas(ProxyFlag::class, [
        'id' => $flag->id,
        'review_status' => ReviewStatus::Approved->value,
        'reviewer_notes' => 'Looks legitimate.',
        'reviewer_id' => $admin->id,
    ]);
});

test('admin_can_reject_proxy_flag_with_required_note', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('reject')->table($flag), [
            'reviewer_notes' => 'Suspicious activity confirmed.',
        ])
        ->assertNotified();

    assertDatabaseHas(ProxyFlag::class, [
        'id' => $flag->id,
        'review_status' => ReviewStatus::Rejected->value,
        'reviewer_notes' => 'Suspicious activity confirmed.',
    ]);
});

test('reject_without_notes_fails_validation', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('reject')->table($flag), [
            'reviewer_notes' => null,
        ])
        ->assertHasActionErrors(['reviewer_notes' => 'required']);
});

test('approve_action_writes_audit_log', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('approve')->table($flag), [
            'reviewer_notes' => 'All clear.',
        ]);

    assertDatabaseHas(AuditLog::class, [
        'action' => 'proxy_flag.approved',
        'entity_type' => ProxyFlag::class,
        'entity_id' => $flag->id,
        'actor_id' => $admin->id,
    ]);
});

test('reject_action_writes_audit_log', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('reject')->table($flag), [
            'reviewer_notes' => 'Definite proxy.',
        ]);

    assertDatabaseHas(AuditLog::class, [
        'action' => 'proxy_flag.rejected',
        'entity_type' => ProxyFlag::class,
        'entity_id' => $flag->id,
        'actor_id' => $admin->id,
    ]);
});

test('bulk_approve_updates_all_selected_flags', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);
    $flags = ProxyFlag::factory()->count(3)->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callTableBulkAction('bulk_approve', $flags, [
            'reviewer_notes' => 'Batch approved.',
        ])
        ->assertNotified();

    foreach ($flags as $flag) {
        assertDatabaseHas(ProxyFlag::class, [
            'id' => $flag->id,
            'review_status' => ReviewStatus::Approved->value,
        ]);
    }
});

test('bulk_reject_requires_reason', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);
    $flags = ProxyFlag::factory()->count(3)->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callTableBulkAction('bulk_reject', $flags, [
            'reviewer_notes' => null,
        ])
        ->assertHasActionErrors(['reviewer_notes' => 'required']);
});

test('navigation_badge_shows_pending_count', function () {
    $dept = Department::factory()->create();
    $admin = adminForProxyFlag($dept);

    ProxyFlag::factory()->count(3)->pending()->create();
    ProxyFlag::factory()->count(2)->create(['review_status' => ReviewStatus::Approved]);

    $this->actingAs($admin);

    expect(ProxyFlagResource::getNavigationBadge())->toBe('3');
});
