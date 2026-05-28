<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'code',
        'name',
        'semester',
        'credits',
        'min_attendance_pct',
    ];

    protected function casts(): array
    {
        return [
            'min_attendance_pct' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function classGroups(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function timetables(): HasMany
    {
        return $this->hasMany(Timetable::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
