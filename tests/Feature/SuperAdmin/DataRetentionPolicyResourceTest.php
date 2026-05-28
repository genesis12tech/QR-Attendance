<?php

use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages\EditDataRetentionPolicy;
use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages\ListDataRetentionPolicies;
use App\Models\DataRetentionPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_retention_policies', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policies = DataRetentionPolicy::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListDataRetentionPolicies::class)
        ->assertCanSeeTableRecords($policies);
});

test('super_admin_can_edit_retention_policy', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = DataRetentionPolicy::factory()->create(['retention_days' => 365]);

    $this->actingAs($superAdmin);

    livewire(EditDataRetentionPolicy::class, ['record' => $policy->id])
        ->fillForm(['retention_days' => 730])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(DataRetentionPolicy::class, ['id' => $policy->id, 'retention_days' => 730]);
});

test('retention_days_must_be_positive_integer', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = DataRetentionPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditDataRetentionPolicy::class, ['record' => $policy->id])
        ->fillForm(['retention_days' => 0])
        ->call('save')
        ->assertHasFormErrors(['retention_days']);
});
