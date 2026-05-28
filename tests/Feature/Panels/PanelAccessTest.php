<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_access_super_admin_panel', function () {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get('/super-admin');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->not->toContain('/login');
});

test('admin_is_redirected_from_super_admin_panel', function () {
    $user = User::factory()->admin()->create();

    $this->actingAs($user)->get('/super-admin')->assertForbidden();
});

test('faculty_is_redirected_from_admin_panel', function () {
    $user = User::factory()->faculty()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('super_admin_is_redirected_from_faculty_panel', function () {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)->get('/faculty')->assertForbidden();
});

test('unauthenticated_user_is_redirected_to_login', function () {
    $this->get('/super-admin')->assertRedirect('/super-admin/login');
});
