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
        ];
    }
}
