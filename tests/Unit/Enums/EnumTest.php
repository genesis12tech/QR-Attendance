<?php

use App\Enums\AttendanceStatus;
use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Enums\UserStatus;

test('user role enum backed values match expected strings', function () {
    expect(UserRole::SuperAdmin->value)->toBe('super_admin')
        ->and(UserRole::Admin->value)->toBe('admin')
        ->and(UserRole::Faculty->value)->toBe('faculty')
        ->and(UserRole::Student->value)->toBe('student');
});

test('user status enum backed values match expected strings', function () {
    expect(UserStatus::Active->value)->toBe('active')
        ->and(UserStatus::Suspended->value)->toBe('suspended')
        ->and(UserStatus::Inactive->value)->toBe('inactive');
});

test('attendance status has pending review case', function () {
    expect(AttendanceStatus::PendingReview->value)->toBe('pending_review');
});

test('day of week has seven cases', function () {
    expect(DayOfWeek::cases())->toHaveCount(7);
});
