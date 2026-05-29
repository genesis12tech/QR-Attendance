<?php

namespace Database\Factories;

use App\Models\SecurityPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityPolicy>
 */
class SecurityPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'policy_name' => 'default',
            'qr_expiry_seconds' => 30,
            'risk_auto_reject' => 80,
            'risk_pending_review' => 50,
            'late_threshold_mins' => 10,
            'geofence_radius_m' => 50,
            'device_binding_required' => true,
            'clock_skew_seconds' => 5,
            'is_active' => true,
            'w_gps' => 20,
            'w_device' => 20,
            'w_clock_skew' => 20,
            'w_wifi' => 20,
            'w_beacon' => 20,
            'w_ip_cluster' => 20,
            'w_speed' => 20,
            'w_peer_scan' => 20,
            'w_biometric' => 20,
        ];
    }
}
