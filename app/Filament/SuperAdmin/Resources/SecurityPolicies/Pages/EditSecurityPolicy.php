<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages;

use App\Concerns\LogsToAudit;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\SecurityPolicyResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;

class EditSecurityPolicy extends EditRecord
{
    use LogsToAudit;

    protected static string $resource = SecurityPolicyResource::class;

    protected array $oldValues = [];

    protected function beforeSave(): void
    {
        $this->oldValues = $this->record->only([
            'qr_expiry_seconds', 'risk_auto_reject', 'risk_pending_review',
            'late_threshold_mins', 'geofence_radius_m', 'device_binding_required',
            'clock_skew_seconds', 'is_active',
        ]);
    }

    protected function afterSave(): void
    {
        Cache::forget('security_policy.active');

        $this->logAudit(
            'security_policy.updated',
            $this->record,
            $this->oldValues,
            $this->record->only([
                'qr_expiry_seconds', 'risk_auto_reject', 'risk_pending_review',
                'late_threshold_mins', 'geofence_radius_m', 'device_binding_required',
                'clock_skew_seconds', 'is_active',
            ])
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
