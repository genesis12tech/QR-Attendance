<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies;

use App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages\EditSecurityPolicy;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages\ListSecurityPolicies;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\Schemas\SecurityPolicyForm;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\Tables\SecurityPoliciesTable;
use App\Models\SecurityPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SecurityPolicyResource extends Resource
{
    protected static ?string $model = SecurityPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return SecurityPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecurityPoliciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSecurityPolicies::route('/'),
            'edit' => EditSecurityPolicy::route('/{record}/edit'),
        ];
    }
}
