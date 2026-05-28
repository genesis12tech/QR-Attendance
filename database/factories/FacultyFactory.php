<?php

namespace Database\Factories;

use App\Enums\FacultyStatus;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->faculty(),
            'department_id' => Department::factory(),
            'employee_code' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
            'designation' => fake()->jobTitle(),
            'status' => FacultyStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FacultyStatus::Active,
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FacultyStatus::OnLeave,
        ]);
    }
}
