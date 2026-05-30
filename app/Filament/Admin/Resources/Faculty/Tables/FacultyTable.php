<?php

namespace App\Filament\Admin\Resources\Faculty\Tables;

use App\Enums\FacultyStatus;
use App\Models\Faculty;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FacultyTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee_code')
                    ->label('Employee Code')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),
                TextColumn::make('designation')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('attendance_sessions_count')
                    ->label('Sessions')
                    ->counts('attendanceSessions')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(FacultyStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewSessions')
                    ->label('Sessions')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->url(fn (Faculty $record) => '/admin/attendance-sessions?tableFilters%5Bfaculty_id%5D%5Bvalue%5D='.$record->id),
                Action::make('viewTimetable')
                    ->label('Timetable')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->url(fn (Faculty $record) => '/admin/timetables?tableFilters%5Bfaculty_id%5D%5Bvalue%5D='.$record->id),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No faculty found')
            ->emptyStateDescription('Add the first faculty member using the button above.');
    }
}
