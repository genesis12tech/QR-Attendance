<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Schemas;

use App\Enums\AdminAssignmentRole;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class AdminRoleAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('User')
                ->relationship('user', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('assigned_by')
                ->label('Assigned By')
                ->options(fn () => User::pluck('name', 'id'))
                ->searchable()
                ->required(),
            Select::make('role')
                ->options(AdminAssignmentRole::class)
                ->required(),
            Select::make('department_id')
                ->label('Department')
                ->relationship('department', 'name')
                ->searchable()
                ->preload()
                ->required(),
            DateTimePicker::make('assigned_at')
                ->required()
                ->default(now()),
        ]);
    }
}
