<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => strtoupper(fake()->lexify('??')).'-'.fake()->numberBetween(100, 999),
            'building' => fake()->word(),
            'capacity' => 40,
            'latitude' => null,
            'longitude' => null,
            'geofence_radius_m' => null,
            'beacon_id' => null,
            'wifi_ssid' => null,
            'is_active' => true,
        ];
    }

    public function withGeofence(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'geofence_radius_m' => 50,
        ]);
    }
}
