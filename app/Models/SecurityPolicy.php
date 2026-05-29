<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SecurityPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_name',
        'qr_expiry_seconds',
        'risk_auto_reject',
        'risk_pending_review',
        'late_threshold_mins',
        'geofence_radius_m',
        'device_binding_required',
        'clock_skew_seconds',
        'is_active',
        'w_gps',
        'w_device',
        'w_clock_skew',
        'w_wifi',
        'w_beacon',
        'w_ip_cluster',
        'w_speed',
        'w_peer_scan',
        'w_biometric',
    ];

    protected function casts(): array
    {
        return [
            'device_binding_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('security_policy.active'));
        static::deleted(fn () => Cache::forget('security_policy.active'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function getActive(): ?static
    {
        return Cache::remember('security_policy.active', 60, fn () => static::active()->first());
    }
}
