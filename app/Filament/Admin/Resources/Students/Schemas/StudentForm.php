<?php

namespace App\Filament\Admin\Resources\Students\Schemas;

use App\Enums\StudentStatus;
use App\Models\Department;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('roll_no')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            Select::make('user_id')
                ->label('User')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload(false)
                ->required(),
            Select::make('department_id')
                ->label('Department')
                ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('batch_year')
                ->required()
                ->maxLength(4),
            TextInput::make('section')
                ->maxLength(10),
            Select::make('status')
                ->options(StudentStatus::class)
                ->default(StudentStatus::Active->value)
                ->required(),
        ]);
    }
}
