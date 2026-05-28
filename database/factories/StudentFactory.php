<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'department_id' => Department::factory(),
            'roll_no' => fake()->year().str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'batch_year' => fake()->year(),
            'section' => fake()->randomElement(['A', 'B', 'C']),
            'status' => StudentStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentStatus::Active,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentStatus::Suspended,
        ]);
    }

    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentStatus::Graduated,
        ]);
    }
}
