<?php

namespace App\Filament\Faculty\Resources\SessionExports;

use App\Filament\Faculty\Resources\SessionExports\Pages\ListSessionExports;
use App\Filament\Faculty\Resources\SessionExports\Tables\SessionExportsTable;
use App\Models\SessionExport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SessionExportResource extends Resource
{
    protected static ?string $model = SessionExport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|\UnitEnum|null $navigationGroup = 'Records';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['session.course'])
            ->where('requested_by', auth()->id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return SessionExportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSessionExports::route('/'),
        ];
    }
}
