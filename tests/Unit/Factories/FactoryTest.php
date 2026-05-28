<?php

use App\Enums\ProxySeverity;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\ProxyFlag;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('user factory creates user with correct role via state', function () {
    expect(User::factory()->superAdmin()->create()->role)->toBe(UserRole::SuperAdmin)
        ->and(User::factory()->admin()->create()->role)->toBe(UserRole::Admin)
        ->and(User::factory()->faculty()->create()->role)->toBe(UserRole::Faculty)
        ->and(User::factory()->student()->create()->role)->toBe(UserRole::Student);
});

test('student factory creates related user and department', function () {
    $student = Student::factory()->create();

    expect($student->user)->toBeInstanceOf(User::class)
        ->and($student->department)->toBeInstanceOf(Department::class);
});

test('attendance session factory active state sets started at', function () {
    $session = AttendanceSession::factory()->active()->create();

    expect($session->status)->toBe(SessionStatus::Active)
        ->and($session->started_at)->not->toBeNull();
});

test('proxy flag factory critical state sets correct severity', function () {
    $flag = ProxyFlag::factory()->critical()->create();

    expect($flag->severity)->toBe(ProxySeverity::Critical)
        ->and($flag->risk_score)->toBeGreaterThanOrEqual(80);
});
