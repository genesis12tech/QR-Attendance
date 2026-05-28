<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SecurityPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('policy_name')->sortable(),
                TextColumn::make('qr_expiry_seconds')->label('QR Expiry (s)'),
                TextColumn::make('risk_auto_reject')->label('Auto-Reject Score'),
                TextColumn::make('risk_pending_review')->label('Pending Review Score'),
                TextColumn::make('late_threshold_mins')->label('Late Threshold (m)'),
                IconColumn::make('device_binding_required')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
