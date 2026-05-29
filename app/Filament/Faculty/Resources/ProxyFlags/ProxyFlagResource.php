<?php

namespace App\Filament\Faculty\Resources\ProxyFlags;

use App\Filament\Faculty\Resources\ProxyFlags\Pages\ListProxyFlags;
use App\Filament\Faculty\Resources\ProxyFlags\Tables\ProxyFlagTable;
use App\Models\ProxyFlag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProxyFlagResource extends Resource
{
    protected static ?string $model = ProxyFlag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|\UnitEnum|null $navigationGroup = 'My Sessions';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $facultyId = auth()->user()?->faculty?->id;

        return parent::getEloquentQuery()
            ->with([
                'attendanceRecord.student.user',
                'attendanceRecord.session.course',
            ])
            ->whereHas(
                'attendanceRecord.session',
                fn (Builder $q) => $q->where('faculty_id', $facultyId ?? 0)
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return ProxyFlagTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProxyFlags::route('/'),
        ];
    }
}
