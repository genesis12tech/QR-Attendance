<?php

namespace Database\Factories;

use App\Models\DataRetentionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataRetentionPolicy>
 */
class DataRetentionPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'entity_type' => $this->faker->randomElement(['attendance_records', 'audit_logs', 'session_exports']),
            'retention_days' => $this->faker->numberBetween(30, 730),
            'is_active' => true,
            'last_run_at' => null,
        ];
    }
}
