<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Timetable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timetable>
 */
class TimetableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'class_group_id' => ClassGroup::factory(),
            'faculty_id' => Faculty::factory(),
            'room_id' => Room::factory(),
            'day_of_week' => fake()->randomElement(DayOfWeek::cases())->value,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'effective_from' => now()->toDateString(),
            'effective_until' => null,
        ];
    }
}
