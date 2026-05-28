<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdminUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->required(fn (string $context) => $context === 'create')
                ->confirmed()
                ->dehydrated(fn ($state) => filled($state)),
            TextInput::make('password_confirmation')
                ->password()
                ->required(fn (string $context) => $context === 'create')
                ->dehydrated(false),
            Select::make('role')
                ->options([
                    UserRole::Admin->value => 'Admin',
                    UserRole::Faculty->value => 'Faculty',
                ])
                ->required(),
            Select::make('status')
                ->options(UserStatus::class)
                ->default(UserStatus::Active->value)
                ->required(),
        ]);
    }
}
