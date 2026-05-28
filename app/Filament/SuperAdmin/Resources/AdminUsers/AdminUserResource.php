<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers;

use App\Enums\UserRole;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Filament\SuperAdmin\Resources\AdminUsers\Schemas\AdminUserForm;
use App\Filament\SuperAdmin\Resources\AdminUsers\Tables\AdminUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Admin Users';

    protected static ?string $modelLabel = 'Admin User';

    protected static ?string $pluralModelLabel = 'Admin Users';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('activeAdminAssignment.department')
            ->whereIn('role', [UserRole::Admin->value, UserRole::Faculty->value]);
    }

    public static function form(Schema $schema): Schema
    {
        return AdminUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
