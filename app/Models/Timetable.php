<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'class_group_id',
        'faculty_id',
        'room_id',
        'day_of_week',
        'start_time',
        'end_time',
        'effective_from',
        'effective_until',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => DayOfWeek::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
