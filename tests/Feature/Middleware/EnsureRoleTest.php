<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Route::get('/_test/super-admin', fn () => 'ok')
        ->middleware(EnsureRole::class.':super_admin');

    Route::get('/_test/admin', fn () => 'ok')
        ->middleware(EnsureRole::class.':admin');
});

test('super_admin_role_passes_super_admin_check', function () {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $this->actingAs($user)->get('/_test/super-admin')->assertSuccessful();
});

test('admin_role_is_blocked_from_super_admin_panel', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)->get('/_test/super-admin')->assertForbidden();
});

test('faculty_role_is_blocked_from_admin_panel', function () {
    $user = User::factory()->create(['role' => UserRole::Faculty]);

    $this->actingAs($user)->get('/_test/admin')->assertForbidden();
});

test('unauthenticated_user_receives_401', function () {
    $this->get('/_test/super-admin')->assertStatus(401);
});

test('suspended_user_is_blocked', function () {
    $user = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'status' => UserStatus::Suspended,
    ]);

    $this->actingAs($user)->get('/_test/super-admin')->assertForbidden();
});
