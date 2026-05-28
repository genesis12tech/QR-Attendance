<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_fingerprint',
        'device_type',
        'platform',
        'is_primary',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'registered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'device_id');
    }
}
