<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'faculty_id',
        'course_id',
        'class_group_id',
        'room_id',
        'timetable_id',
        'status',
        'started_at',
        'closed_at',
        'close_reason',
        'total_enrolled',
        'total_present',
        'total_late',
        'total_absent',
    ];

    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $session) => $session->uuid ??= (string) Str::uuid());
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }

    public function sessionExports(): HasMany
    {
        return $this->hasMany(SessionExport::class, 'session_id');
    }
}
