<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRetentionPolicy extends Model
{
    protected $fillable = [
        'entity_type',
        'retention_days',
        'is_active',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }
}
