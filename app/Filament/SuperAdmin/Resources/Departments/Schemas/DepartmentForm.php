<?php

namespace App\Filament\SuperAdmin\Resources\Departments\Schemas;

use App\Models\Faculty;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->required()
                ->maxLength(10)
                ->unique(ignoreRecord: true),
            Select::make('head_faculty_id')
                ->label('Head of Faculty')
                ->relationship('headFaculty')
                ->getOptionLabelFromRecordUsing(fn (Faculty $record) => $record->user?->name ?? 'Unknown')
                ->searchable()
                ->preload()
                ->nullable(),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }
}
