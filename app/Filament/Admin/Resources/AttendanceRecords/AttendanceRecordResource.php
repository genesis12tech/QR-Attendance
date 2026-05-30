<?php

namespace App\Filament\Admin\Resources\AttendanceRecords;

use App\Filament\Admin\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Filament\Admin\Resources\AttendanceRecords\Tables\AttendanceRecordTable;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    public static function getEloquentQuery(): Builder
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        return parent::getEloquentQuery()
            ->with(['student.user', 'session.course', 'overriddenBy'])
            ->when(
                $departmentId,
                fn (Builder $query) => $query->whereHas(
                    'session.course',
                    fn (Builder $q) => $q->where('department_id', $departmentId)
                )
            );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
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
