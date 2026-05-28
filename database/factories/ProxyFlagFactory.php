<?php

namespace Database\Factories;

use App\Enums\ProxySeverity;
use App\Enums\ReviewStatus;
use App\Models\AttendanceRecord;
use App\Models\ProxyFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProxyFlag>
 */
class ProxyFlagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory()->pendingReview(),
            'severity' => ProxySeverity::Medium,
            'reason_code' => fake()->randomElement(['gps_mismatch', 'device_mismatch', 'clock_skew']),
            'risk_score' => 60,
            'evidence_json' => null,
            'review_status' => ReviewStatus::Pending,
            'reviewer_id' => null,
            'reviewer_notes' => null,
            'reviewed_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'review_status' => ReviewStatus::Pending,
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => ProxySeverity::Critical,
            'risk_score' => fake()->numberBetween(80, 100),
        ]);
    }
}
