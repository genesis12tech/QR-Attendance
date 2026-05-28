<?php

namespace App\Models;

use App\Enums\AdminAssignmentRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRoleAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assigned_by',
        'role',
        'department_id',
        'assigned_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => AdminAssignmentRole::class,
            'assigned_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
