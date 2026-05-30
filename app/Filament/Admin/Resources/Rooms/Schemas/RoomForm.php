<?php

namespace App\Filament\Admin\Resources\Rooms\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(100),
            TextInput::make('building')
                ->maxLength(100),
            TextInput::make('capacity')
                ->numeric()
                ->minValue(1),
            TextInput::make('latitude')
                ->numeric(),
            TextInput::make('longitude')
                ->numeric(),
            TextInput::make('geofence_radius_m')
                ->label('Geofence Radius (m)')
                ->numeric()
                ->minValue(1),
            TextInput::make('beacon_id')
                ->label('Beacon ID')
                ->maxLength(100),
            TextInput::make('wifi_ssid')
                ->label('Wi-Fi SSID')
                ->maxLength(100),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }
}
