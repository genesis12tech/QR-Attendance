<?php

namespace App\Filament\Faculty\Resources\AttendanceRecords;

use App\Filament\Faculty\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Filament\Faculty\Resources\AttendanceRecords\Tables\AttendanceRecordTable;
use App\Models\AttendanceRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceRecordResource extends Resource
{
    protected static ?string $model = AttendanceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Records';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $facultyId = auth()->user()?->faculty?->id;

        return parent::getEloquentQuery()
            ->with(['student.user', 'session.course'])
            ->whereHas(
                'session',
                fn (Builder $q) => $q->where('faculty_id', $facultyId ?? 0)
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return AttendanceRecordTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceRecords::route('/'),
        ];
    }
}
