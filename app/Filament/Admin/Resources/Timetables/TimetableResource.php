<?php

namespace App\Filament\Admin\Resources\Timetables;

use App\Filament\Admin\Resources\Timetables\Pages\CreateTimetable;
use App\Filament\Admin\Resources\Timetables\Pages\EditTimetable;
use App\Filament\Admin\Resources\Timetables\Pages\ListTimetables;
use App\Filament\Admin\Resources\Timetables\Schemas\TimetableForm;
use App\Filament\Admin\Resources\Timetables\Tables\TimetablesTable;
use App\Models\Timetable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TimetableResource extends Resource
{
    protected static ?string $model = Timetable::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Management';

    public static function form(Schema $schema): Schema
    {
        return TimetableForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TimetablesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTimetables::route('/'),
            'create' => CreateTimetable::route('/create'),
            'edit' => EditTimetable::route('/{record}/edit'),
        ];
    }
}
