<?php

namespace App\Filament\Faculty\Resources\AttendanceRecords\Tables;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendanceRecordTable
{
    public static function configure(Table $table): Table
    {
        $facultyId = auth()->user()?->faculty?->id;

        return $table
            ->columns([
                TextColumn::make('student.roll_no')
                    ->label('Roll No')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('marked_at')
                    ->label('Marked At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('risk_score')
                    ->label('Risk Score')
                    ->badge()
                    ->color(fn (AttendanceRecord $record): string => match (true) {
                        $record->risk_score >= 80 => 'danger',
                        $record->risk_score >= 50 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::class),
                SelectFilter::make('session_id')
                    ->label('Session')
                    ->options(fn () => AttendanceSession::query()
                        ->where('faculty_id', $facultyId ?? 0)
                        ->with('course')
                        ->get()
                        ->mapWithKeys(fn (AttendanceSession $s) => [
                            $s->id => $s->course?->code.' — '.($s->started_at?->format('d M Y H:i') ?? 'pending'),
                        ])
                        ->all()
                    ),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No attendance records found');
    }
}
