<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SecurityPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('policy_name')
                ->required()
                ->maxLength(255),
            TextInput::make('qr_expiry_seconds')
                ->numeric()
                ->required()
                ->minValue(10)
                ->maxValue(300),
            TextInput::make('risk_auto_reject')
                ->numeric()
                ->required()
                ->minValue(50)
                ->maxValue(100),
            TextInput::make('risk_pending_review')
                ->numeric()
                ->required()
                ->minValue(20)
                ->maxValue(79),
            TextInput::make('late_threshold_mins')
                ->numeric()
                ->required()
                ->minValue(1),
            TextInput::make('geofence_radius_m')
                ->numeric()
                ->required()
                ->minValue(10),
            TextInput::make('clock_skew_seconds')
                ->numeric()
                ->required()
                ->minValue(0),
            Toggle::make('device_binding_required'),
            Toggle::make('is_active'),
        ]);
    }
}
