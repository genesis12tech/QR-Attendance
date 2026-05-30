<?php

namespace App\Filament\Admin\Resources\Faculty\Schemas;

use App\Enums\FacultyStatus;
use App\Models\Department;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FacultyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('User')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload(false)
                ->required(),
            TextInput::make('employee_code')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            Select::make('department_id')
                ->label('Department')
                ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('designation')
                ->maxLength(100),
            Select::make('status')
                ->options(FacultyStatus::class)
                ->default(FacultyStatus::Active->value)
                ->required(),
        ]);
    }
}
