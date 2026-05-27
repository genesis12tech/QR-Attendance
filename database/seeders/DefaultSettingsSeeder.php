<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DefaultSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSecurityPolicy();
        $this->seedSystemSettings();
        $this->seedDataRetentionPolicies();
    }

    private function seedSecurityPolicy(): void
    {
        DB::table('security_policies')->updateOrInsert(
            ['policy_name' => 'default'],
            [
                'qr_expiry_seconds' => 30,
                'risk_auto_reject' => 80,
                'risk_pending_review' => 50,
                'late_threshold_mins' => 10,
                'geofence_radius_m' => 50,
                'device_binding_required' => true,
                'clock_skew_seconds' => 5,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function seedSystemSettings(): void
    {
        $settings = [
            'app_name' => config('app.name', 'QR Attendance'),
            'qr_rotation_seconds' => '30',
            'max_devices_per_student' => '1',
            'attendance_window_mins' => '120',
            'faculty_can_review_flags' => 'false',
        ];

        foreach ($settings as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function seedDataRetentionPolicies(): void
    {
        $policies = [
            ['entity_type' => 'evidence_json', 'retention_days' => 365],
            ['entity_type' => 'proxy_flags',   'retention_days' => 730],
            ['entity_type' => 'audit_logs',    'retention_days' => 730],
            ['entity_type' => 'qr_challenges', 'retention_days' => 90],
        ];

        foreach ($policies as $policy) {
            DB::table('data_retention_policies')->updateOrInsert(
                ['entity_type' => $policy['entity_type']],
                [
                    'retention_days' => $policy['retention_days'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
