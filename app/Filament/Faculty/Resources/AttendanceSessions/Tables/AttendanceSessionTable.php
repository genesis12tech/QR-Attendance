<?php

namespace App\Filament\Faculty\Resources\AttendanceSessions\Tables;

use App\Enums\SessionStatus;
use App\Filament\Faculty\Pages\QrDisplayPage;
use App\Jobs\FinalizeAttendanceSession;
use App\Jobs\GenerateAttendanceReport;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Component;

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
                    ->sortable(),
                TextColumn::make('room.name')
                    ->label('Room')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Started At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Closed At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('attendance_summary')
                    ->label('Present / Enrolled')
                    ->state(fn (AttendanceSession $record): string => $record->total_present.' / '.$record->total_enrolled),
                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(function (AttendanceSession $record): string {
                        if (! $record->started_at) {
                            return '—';
                        }
                        $end = $record->closed_at ?? now();

                        return $record->started_at->diffForHumans($end, true);
                    }),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('Start')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('success')
                    ->visible(fn (AttendanceSession $record): bool => $record->status === SessionStatus::Pending)
                    ->requiresConfirmation()
                    ->action(function (AttendanceSession $record, Component $livewire): void {
                        AuditLog::record('session.started', $record, ['status' => $record->status->value], ['status' => SessionStatus::Active->value]);
                        $record->update(['status' => SessionStatus::Active, 'started_at' => now()]);
                        Notification::make()->title('Session started')->success()->send();
                        $livewire->redirect(QrDisplayPage::getUrl());
                    }),

                Action::make('view_qr')
                    ->label('View QR')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('info')
                    ->visible(fn (AttendanceSession $record): bool => $record->status === SessionStatus::Active)
                    ->url(fn (): string => QrDisplayPage::getUrl()),

                Action::make('close')
                    ->label('Close')
                    ->icon(Heroicon::OutlinedStopCircle)
                    ->color('danger')
                    ->visible(fn (AttendanceSession $record): bool => in_array($record->status, [SessionStatus::Active, SessionStatus::Paused]))
                    ->schema([
                        Textarea::make('close_reason')
                            ->label('Reason (optional)')
                            ->rows(3),
                    ])
                    ->action(function (AttendanceSession $record, array $data): void {
                        $record->update([
                            'close_reason' => $data['close_reason'] ?? null,
                        ]);
                        FinalizeAttendanceSession::dispatch($record);
                        Notification::make()->title('Session closing…')->success()->send();
                    }),

                Action::make('export')
                    ->label('Export')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (AttendanceSession $record): bool => $record->status === SessionStatus::Closed)
                    ->schema([
                        Select::make('format')
                            ->label('Format')
                            ->options([
                                'pdf' => 'PDF',
                                'csv' => 'CSV',
                                'xlsx' => 'XLSX',
                            ])
                            ->required(),
                    ])
                    ->action(function (AttendanceSession $record, array $data): void {
                        GenerateAttendanceReport::dispatch(
                            type: 'course',
                            departmentId: $record->course?->department_id,
                            courseId: $record->course_id,
                            from: $record->started_at?->toDateString(),
                            to: $record->closed_at?->toDateString(),
                            format: $data['format'],
                            requestedBy: auth()->id(),
                        );
                        Notification::make()->title('Export queued')->success()->send();
                    }),

                Action::make('reopen')
                    ->label('Reopen')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (AttendanceSession $record): bool => $record->status === SessionStatus::Closed
                        && $record->closed_at !== null
                        && $record->closed_at->gte(now()->subMinutes(15))
                    )
                    ->requiresConfirmation()
                    ->action(function (AttendanceSession $record): void {
                        AuditLog::record('session.reopened', $record, ['status' => $record->status->value], ['status' => SessionStatus::Active->value]);
                        $record->update(['status' => SessionStatus::Active, 'closed_at' => null]);
                        Notification::make()->title('Session reopened')->success()->send();
                    }),
            ])
            ->defaultSort('started_at', 'desc')
            ->emptyStateHeading('No sessions found');
    }
}
