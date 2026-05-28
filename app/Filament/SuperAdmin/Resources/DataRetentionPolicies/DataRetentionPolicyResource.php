<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies;

use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages\EditDataRetentionPolicy;
use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages\ListDataRetentionPolicies;
use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Schemas\DataRetentionPolicyForm;
use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Tables\DataRetentionPoliciesTable;
use App\Models\DataRetentionPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DataRetentionPolicyResource extends Resource
{
    protected static ?string $model = DataRetentionPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DataRetentionPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DataRetentionPoliciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataRetentionPolicies::route('/'),
            'edit' => EditDataRetentionPolicy::route('/{record}/edit'),
        ];
    }
}
