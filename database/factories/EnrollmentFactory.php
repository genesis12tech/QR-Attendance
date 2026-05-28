<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'class_group_id' => ClassGroup::factory(),
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now()->toDateString(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Active,
        ]);
    }

    public function dropped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Dropped,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EnrollmentStatus::Completed,
        ]);
    }
}
