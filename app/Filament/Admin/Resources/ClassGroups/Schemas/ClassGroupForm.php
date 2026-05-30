<?php

namespace App\Filament\Admin\Resources\ClassGroups\Schemas;

use App\Models\Course;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ClassGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(50),
            Select::make('course_id')
                ->label('Course')
                ->options(fn () => Course::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
        ]);
    }
}
