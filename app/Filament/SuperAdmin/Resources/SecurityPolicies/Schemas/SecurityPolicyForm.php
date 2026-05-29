<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SecurityPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General Settings')
                ->schema([
                    TextInput::make('policy_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('qr_expiry_seconds')
                        ->label('QR Expiry (seconds)')
                        ->numeric()
                        ->required()
                        ->minValue(10)
                        ->maxValue(300),
                    TextInput::make('risk_auto_reject')
                        ->label('Auto-Reject Score (50–100)')
                        ->numeric()
                        ->required()
                        ->minValue(50)
                        ->maxValue(100),
                    TextInput::make('risk_pending_review')
                        ->label('Pending Review Score (20–79)')
                        ->numeric()
                        ->required()
                        ->minValue(20)
                        ->maxValue(79),
                    TextInput::make('late_threshold_mins')
                        ->label('Late Threshold (minutes)')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    TextInput::make('geofence_radius_m')
                        ->label('Geofence Radius (metres)')
                        ->numeric()
                        ->required()
                        ->minValue(10),
                    TextInput::make('clock_skew_seconds')
                        ->label('Clock Skew Tolerance (seconds)')
                        ->numeric()
                        ->required()
                        ->minValue(0),
                    Toggle::make('device_binding_required'),
                    Toggle::make('is_active'),
                ]),

            Section::make('Proxy Signal Weights')
                ->description('Each weight (0–100) determines how much a signal contributes to the proxy risk score.')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('w_gps')
                                ->label('GPS Location')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                            TextInput::make('w_device')
                                ->label('Device Fingerprint')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                            TextInput::make('w_clock_skew')
                                ->label('Clock Skew')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                            TextInput::make('w_wifi')
                                ->label('WiFi SSID')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                            TextInput::make('w_beacon')
                                ->label('Bluetooth Beacon')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                            TextInput::make('w_ip_cluster')
                                ->label('IP Cluster')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                            TextInput::make('w_speed')
                                ->label('Movement Speed')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                            TextInput::make('w_peer_scan')
                                ->label('Peer Scan')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                            TextInput::make('w_biometric')
                                ->label('Biometric')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100)
                                ->columnSpan(1),
                        ]),
                ]),
        ]);
    }
}
