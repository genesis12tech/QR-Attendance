<?php

use App\Filament\SuperAdmin\Pages\SystemSettingsPage;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_view_system_settings_page', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get('/super-admin/system-settings')
        ->assertSuccessful();
});

test('system_settings_are_pre_populated_from_database', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    SystemSetting::set('app_name', 'My App');

    $this->actingAs($superAdmin);

    livewire(SystemSettingsPage::class)
        ->assertFormSet(['app_name' => 'My App']);
});

test('super_admin_can_save_system_settings', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(SystemSettingsPage::class)
        ->fillForm(['app_name' => 'Updated App', 'qr_rotation_seconds' => '45'])
        ->call('save')
        ->assertNotified();

    expect(SystemSetting::get('app_name'))->toBe('Updated App');
    expect(SystemSetting::get('qr_rotation_seconds'))->toBe('45');
});

test('faculty_can_review_flags_toggle_persists', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(SystemSettingsPage::class)
        ->fillForm(['faculty_can_review_flags' => true])
        ->call('save');

    expect(SystemSetting::get('faculty_can_review_flags'))->toBe('true');
});
