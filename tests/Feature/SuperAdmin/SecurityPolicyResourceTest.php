<?php

use App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages\EditSecurityPolicy;
use App\Models\AuditLog;
use App\Models\SecurityPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_edit_security_policy', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30]);

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 60])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(SecurityPolicy::class, ['id' => $policy->id, 'qr_expiry_seconds' => 60]);
});

test('qr_expiry_seconds_must_be_between_10_and_300', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 5])
        ->call('save')
        ->assertHasFormErrors(['qr_expiry_seconds']);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 400])
        ->call('save')
        ->assertHasFormErrors(['qr_expiry_seconds']);
});

test('risk_auto_reject_must_be_between_50_and_100', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['risk_auto_reject' => 40])
        ->call('save')
        ->assertHasFormErrors(['risk_auto_reject']);
});

test('risk_pending_review_must_be_between_20_and_79', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['risk_pending_review' => 90])
        ->call('save')
        ->assertHasFormErrors(['risk_pending_review']);
});

test('save_clears_security_policy_cache', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();
    Cache::put('security_policy.active', $policy, 60);

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 45])
        ->call('save');

    expect(Cache::has('security_policy.active'))->toBeFalse();
});

test('save_writes_audit_log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30]);

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 60])
        ->call('save');

    expect(
        AuditLog::where('action', 'security_policy.updated')
            ->where('entity_id', $policy->id)
            ->exists()
    )->toBeTrue();
});

test('proxy_signal_weights_can_be_saved', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm([
            'w_gps' => 30,
            'w_device' => 25,
            'w_clock_skew' => 15,
            'w_wifi' => 10,
            'w_beacon' => 10,
            'w_ip_cluster' => 5,
            'w_speed' => 5,
            'w_peer_scan' => 0,
            'w_biometric' => 0,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(SecurityPolicy::class, [
        'id' => $policy->id,
        'w_gps' => 30,
        'w_biometric' => 0,
    ]);
});

test('proxy_signal_weight_rejects_value_above_100', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['w_gps' => 101])
        ->call('save')
        ->assertHasFormErrors(['w_gps']);
});
