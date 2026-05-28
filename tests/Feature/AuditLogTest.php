<?php

use App\Concerns\LogsToAudit;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('audit_log_record_persists_to_database', function () {
    $user = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create();

    AuditLog::record('dept.created', $dept, [], ['name' => $dept->name], $user);

    expect(AuditLog::count())->toBe(1);
});

test('audit_log_captures_actor_id_and_role', function () {
    $user = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    AuditLog::record('dept.updated', $dept, [], [], $user);

    $log = AuditLog::first();
    expect($log->actor_id)->toBe($user->id);
    expect($log->actor_role)->toBe('admin');
});

test('audit_log_captures_ip_address', function () {
    $user = User::factory()->create();
    $dept = Department::factory()->create();

    $this->actingAs($user);
    AuditLog::record('dept.viewed', $dept, [], [], $user);

    expect(AuditLog::first()->ip_address)->not->toBeNull();
});

test('audit_log_stores_old_and_new_values_as_json', function () {
    $user = User::factory()->create();
    $dept = Department::factory()->create();

    AuditLog::record('dept.updated', $dept, ['name' => 'Old'], ['name' => 'New'], $user);

    $log = AuditLog::first();
    expect($log->old_values)->toBe(['name' => 'Old']);
    expect($log->new_values)->toBe(['name' => 'New']);
});

test('audit_log_works_with_null_actor_for_system_actions', function () {
    $dept = Department::factory()->create();

    AuditLog::record('system.cleanup', $dept, [], []);

    $log = AuditLog::first();
    expect($log->actor_id)->toBeNull();
    expect($log->actor_role)->toBeNull();
});

test('logs_to_audit_trait_provides_log_audit_method', function () {
    $user = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create();

    $component = new class
    {
        use LogsToAudit;
    };

    $this->actingAs($user);
    $component->logAudit('dept.created', $dept, [], ['name' => $dept->name]);

    expect(AuditLog::count())->toBe(1);
    expect(AuditLog::first()->action)->toBe('dept.created');
});
