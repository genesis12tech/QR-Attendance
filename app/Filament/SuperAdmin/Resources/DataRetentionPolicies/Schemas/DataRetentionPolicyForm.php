<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DataRetentionPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('entity_type')
                ->required()
                ->maxLength(255),
            TextInput::make('retention_days')
                ->numeric()
                ->required()
                ->minValue(1),
            Toggle::make('is_active'),
        ]);
    }
}
