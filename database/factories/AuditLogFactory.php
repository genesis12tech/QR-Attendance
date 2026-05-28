<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_id' => User::factory(),
            'actor_role' => fake()->randomElement(['super_admin', 'admin', 'faculty']),
            'action' => fake()->randomElement(['user.created', 'session.closed', 'policy.updated', 'record.overridden']),
            'entity_type' => fake()->randomElement(['App\Models\User', 'App\Models\AttendanceSession', 'App\Models\SecurityPolicy']),
            'entity_id' => fake()->numberBetween(1, 100),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
