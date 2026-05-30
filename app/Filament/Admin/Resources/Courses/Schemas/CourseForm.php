<?php

namespace App\Filament\Admin\Resources\Courses\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('department_id')
                ->label('Department')
                ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('code')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->required()
                ->maxLength(200),
            TextInput::make('semester')
                ->required()
                ->maxLength(10),
            TextInput::make('credits')
                ->numeric()
                ->required()
                ->minValue(1)
                ->maxValue(10),
            TextInput::make('min_attendance_pct')
                ->label('Min Attendance %')
                ->hint('75 = 75%')
                ->numeric()
                ->required()
                ->minValue(0)
                ->maxValue(100),
        ]);
    }
}
