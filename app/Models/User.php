<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function faculty(): HasOne
    {
        return $this->hasOne(Faculty::class);
    }

    public function adminRoleAssignments(): HasMany
    {
        return $this->hasMany(AdminRoleAssignment::class);
    }

    public function activeAdminAssignment(): HasOne
    {
        return $this->hasOne(AdminRoleAssignment::class)->ofMany(
            ['assigned_at' => 'max'],
            fn ($query) => $query->whereNull('revoked_at')
        );
    }

    public function deviceRegistrations(): HasMany
    {
        return $this->hasMany(DeviceRegistration::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
