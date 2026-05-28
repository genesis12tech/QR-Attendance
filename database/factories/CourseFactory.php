<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => 'CS-'.fake()->unique()->numberBetween(100, 999),
            'name' => fake()->words(3, true),
            'semester' => (string) fake()->numberBetween(1, 8),
            'credits' => fake()->numberBetween(1, 5),
            'min_attendance_pct' => 75,
        ];
    }

    public function softDeleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
