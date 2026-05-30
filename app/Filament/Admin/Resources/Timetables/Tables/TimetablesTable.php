<?php

namespace App\Filament\Admin\Resources\Timetables\Tables;

use App\Enums\DayOfWeek;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TimetablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.code')
                    ->label('Course')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('classGroup.name')
                    ->label('Class Group')
                    ->sortable(),
                TextColumn::make('faculty.user.name')
                    ->label('Faculty')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('room.name')
                    ->label('Room')
                    ->sortable(),
                TextColumn::make('day_of_week')
                    ->label('Day')
                    ->formatStateUsing(fn ($state) => $state instanceof DayOfWeek
                        ? ucfirst($state->value)
                        : ucfirst((string) $state)),
                TextColumn::make('start_time')
                    ->label('Start'),
                TextColumn::make('end_time')
                    ->label('End'),
                TextColumn::make('effective_from')
                    ->label('Effective From')
                    ->date(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No timetable entries found')
            ->emptyStateDescription('Add the first timetable entry using the button above.');
    }
}
