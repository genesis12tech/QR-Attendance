<?php

namespace Database\Factories;

use App\Models\ClassGroup;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassGroup>
 */
class ClassGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'name' => 'Group '.fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
        ];
    }
}
