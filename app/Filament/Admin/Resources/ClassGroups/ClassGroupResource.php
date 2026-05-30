<?php

namespace App\Filament\Admin\Resources\ClassGroups;

use App\Filament\Admin\Resources\ClassGroups\Pages\CreateClassGroup;
use App\Filament\Admin\Resources\ClassGroups\Pages\EditClassGroup;
use App\Filament\Admin\Resources\ClassGroups\Pages\ListClassGroups;
use App\Filament\Admin\Resources\ClassGroups\Schemas\ClassGroupForm;
use App\Filament\Admin\Resources\ClassGroups\Tables\ClassGroupsTable;
use App\Models\ClassGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassGroupResource extends Resource
{
    protected static ?string $model = ClassGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Management';

    public static function getEloquentQuery(): Builder
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        return parent::getEloquentQuery()
            ->with(['course'])
            ->when(
                $departmentId,
                fn (Builder $query) => $query->whereHas(
                    'course',
                    fn (Builder $q) => $q->where('department_id', $departmentId)
                )
            );
    }

    public static function form(Schema $schema): Schema
    {
        return ClassGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassGroups::route('/'),
            'create' => CreateClassGroup::route('/create'),
            'edit' => EditClassGroup::route('/{record}/edit'),
        ];
    }
}
