<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments;

use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\CreateAdminRoleAssignment;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\EditAdminRoleAssignment;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\ListAdminRoleAssignments;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Schemas\AdminRoleAssignmentForm;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Tables\AdminRoleAssignmentsTable;
use App\Models\AdminRoleAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdminRoleAssignmentResource extends Resource
{
    protected static ?string $model = AdminRoleAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return AdminRoleAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminRoleAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminRoleAssignments::route('/'),
            'create' => CreateAdminRoleAssignment::route('/create'),
            'edit' => EditAdminRoleAssignment::route('/{record}/edit'),
        ];
    }
}
