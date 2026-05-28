<?php

use App\Models\SecurityPolicy;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('security_policy_active_is_cached_after_first_read', function () {
    SecurityPolicy::factory()->create(['is_active' => true]);

    SecurityPolicy::getActive(); // Populate cache

    DB::enableQueryLog();
    SecurityPolicy::getActive(); // Should use cache — no DB query
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

test('security_policy_cache_is_cleared_on_save', function () {
    Cache::spy();

    SecurityPolicy::factory()->create(['is_active' => true]);

    Cache::shouldHaveReceived('forget')->with('security_policy.active');
});

test('system_setting_get_is_cached_after_first_read', function () {
    SystemSetting::create(['key' => 'test_key', 'value' => 'hello']);

    SystemSetting::get('test_key'); // Populate cache

    DB::enableQueryLog();
    $result = SystemSetting::get('test_key'); // Should use cache
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
    expect($result)->toBe('hello');
});

test('system_setting_cache_is_cleared_on_set', function () {
    Cache::spy();

    SystemSetting::set('my_key', 'my_value');

    Cache::shouldHaveReceived('forget')->with('system_setting.my_key');
});
