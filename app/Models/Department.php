<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'head_faculty_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function headFaculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'head_faculty_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function faculty(): HasMany
    {
        return $this->hasMany(Faculty::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
