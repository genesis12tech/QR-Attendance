<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'faculty_id' => Faculty::factory(),
            'course_id' => Course::factory(),
            'class_group_id' => ClassGroup::factory(),
            'room_id' => null,
            'timetable_id' => null,
            'status' => SessionStatus::Pending,
            'started_at' => null,
            'closed_at' => null,
            'close_reason' => null,
            'total_enrolled' => 0,
            'total_present' => 0,
            'total_late' => 0,
            'total_absent' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Pending,
            'started_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Closed,
            'started_at' => now()->subHour(),
            'closed_at' => now(),
            'total_enrolled' => 10,
            'total_present' => 7,
            'total_late' => 2,
            'total_absent' => 1,
        ]);
    }
}
