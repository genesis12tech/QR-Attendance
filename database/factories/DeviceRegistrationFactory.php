<?php

namespace Database\Factories;

use App\Models\DeviceRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceRegistration>
 */
class DeviceRegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_fingerprint' => (string) Str::uuid(),
            'device_type' => 'smartphone',
            'platform' => 'android',
            'is_primary' => false,
            'registered_at' => now(),
        ];
    }

    public function trusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
