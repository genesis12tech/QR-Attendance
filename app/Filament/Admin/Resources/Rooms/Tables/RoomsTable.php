<?php

namespace App\Filament\Admin\Resources\Rooms\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('building')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('capacity')
                    ->sortable(),
                TextColumn::make('geofence_radius_m')
                    ->label('Geofence (m)')
                    ->sortable(),
                TextColumn::make('beacon_id')
                    ->label('Beacon')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? 'Configured' : 'None')
                    ->color(fn (?string $state): string => $state ? 'success' : 'gray'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No rooms found')
            ->emptyStateDescription('Add the first room using the button above.');
    }
}
