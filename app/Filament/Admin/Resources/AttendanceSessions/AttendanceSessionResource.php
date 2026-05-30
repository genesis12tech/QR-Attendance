<?php

namespace App\Filament\Admin\Resources\AttendanceSessions;

use App\Filament\Admin\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Filament\Admin\Resources\AttendanceSessions\Tables\AttendanceSessionTable;
use App\Models\AttendanceSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        return parent::getEloquentQuery()
            ->with(['course', 'classGroup', 'faculty.user', 'room'])
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
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return AttendanceSessionTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceSessions::route('/'),
        ];
    }
}
