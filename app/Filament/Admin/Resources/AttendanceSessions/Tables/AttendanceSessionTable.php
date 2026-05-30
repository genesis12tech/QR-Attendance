<?php

namespace App\Filament\Admin\Resources\AttendanceSessions\Tables;

use App\Models\AttendanceSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceSessionTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('course.code')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('classGroup.name')
                    ->label('Class Group')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('faculty.user.name')
                    ->label('Faculty')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('room.name')
                    ->label('Room')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Started At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Closed At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('attendance_summary')
                    ->label('Present / Enrolled')
                    ->state(fn (AttendanceSession $record): string => $record->total_present.' / '.$record->total_enrolled),
            ])
            ->defaultSort('started_at', 'desc')
            ->emptyStateHeading('No attendance sessions found');
    }
}
