<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_session_id' => AttendanceSession::factory()->active(),
            'student_id' => Student::factory(),
            'enrollment_id' => null,
            'status' => AttendanceStatus::Present,
            'marked_at' => now(),
            'risk_score' => 0,
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Present,
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Late,
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::PendingReview,
            'risk_score' => 60,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Rejected,
        ]);
    }

    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => fake()->numberBetween(80, 100),
        ]);
    }
}
