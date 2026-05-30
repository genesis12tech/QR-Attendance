<?php

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\DeviceRegistration;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

// ── Auth ────────────────────────────────────────────────────────────────────

it('student can register and receives a token', function () {
    $department = Department::factory()->create();

    $response = $this->postJson('/api/v1/student/auth/register', [
        'name' => 'Jane Student',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roll_no' => '2024001',
        'department_id' => $department->id,
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

    expect(User::where('email', 'jane@example.com')->first()->role)->toBe(UserRole::Student);
    expect(Student::whereHas('user', fn ($q) => $q->where('email', 'jane@example.com'))->exists())->toBeTrue();
});

it('registration requires a unique email', function () {
    $department = Department::factory()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/student/auth/register', [
        'name' => 'Jane',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roll_no' => '2024002',
        'department_id' => $department->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('student can login and receives a token', function () {
    $user = User::factory()->student()->create(['password' => bcrypt('secret')]);

    $this->postJson('/api/v1/student/auth/login', [
        'email' => $user->email,
        'password' => 'secret',
    ])->assertOk()
        ->assertJsonStructure(['token']);
});

it('login fails with wrong password', function () {
    $user = User::factory()->student()->create(['password' => bcrypt('correct')]);

    $this->postJson('/api/v1/student/auth/login', [
        'email' => $user->email,
        'password' => 'wrong',
    ])->assertUnauthorized();
});

// ── Devices ─────────────────────────────────────────────────────────────────

it('student can register a device', function () {
    $user = User::factory()->student()->create();
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/student/devices', [
            'device_fingerprint' => 'fp-abc-123',
            'device_name' => 'Pixel 8',
            'platform' => 'android',
        ])->assertCreated()
        ->assertJsonStructure(['id', 'device_fingerprint', 'device_name']);

    expect(DeviceRegistration::where('user_id', $user->id)->exists())->toBeTrue();
});

it('student cannot exceed max devices limit', function () {
    SystemSetting::set('max_devices_per_student', '1');

    $user = User::factory()->student()->create();
    Student::factory()->create(['user_id' => $user->id]);

    DeviceRegistration::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/student/devices', [
            'device_fingerprint' => 'fp-second',
            'device_name' => 'Samsung S24',
            'platform' => 'android',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['device_fingerprint']);
});

it('authenticated student can list own devices', function () {
    $user = User::factory()->student()->create();
    Student::factory()->create(['user_id' => $user->id]);
    DeviceRegistration::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/student/devices')
        ->assertOk()
        ->assertJsonCount(2);
});

it('student can remove own device', function () {
    $user = User::factory()->student()->create();
    Student::factory()->create(['user_id' => $user->id]);
    $device = DeviceRegistration::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/student/devices/{$device->id}")
        ->assertOk();

    expect(DeviceRegistration::find($device->id))->toBeNull();
});
