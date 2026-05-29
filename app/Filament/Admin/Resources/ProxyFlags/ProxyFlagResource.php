<?php

namespace App\Filament\Admin\Resources\ProxyFlags;

use App\Filament\Admin\Resources\ProxyFlags\Pages\ListProxyFlags;
use App\Filament\Admin\Resources\ProxyFlags\Tables\ProxyFlagTable;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::pending()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'attendanceRecord.student.user',
                'attendanceRecord.session.course',
            ])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderBy('created_at', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
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
