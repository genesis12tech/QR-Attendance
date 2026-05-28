<?php

namespace App\Models;

use App\Enums\FacultyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    use HasFactory;

    protected $table = 'faculty';

    protected $fillable = [
        'user_id',
        'department_id',
        'employee_code',
        'designation',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => FacultyStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
