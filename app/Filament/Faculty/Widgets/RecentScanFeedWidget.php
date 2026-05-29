<?php

namespace App\Filament\Faculty\Widgets;

use App\Enums\SessionStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentScanFeedWidget extends TableWidget
{
    protected ?string $pollingInterval = '3s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $facultyId = auth()->user()?->faculty?->id;

        $activeSession = AttendanceSession::where('faculty_id', $facultyId)
            ->where('status', SessionStatus::Active)
            ->latest()
            ->first();

        $latestIds = $activeSession
            ? AttendanceRecord::where('session_id', $activeSession->id)
                ->latest('marked_at')
                ->limit(10)
                ->pluck('id')
            : collect();

        return $table
            ->heading('Recent Scans')
            ->query(
                AttendanceRecord::query()
                    ->with(['student.user'])
                    ->when(
                        $latestIds->isNotEmpty(),
                        fn (Builder $q) => $q->whereIn('id', $latestIds)->latest('marked_at'),
                        fn (Builder $q) => $q->whereRaw('1 = 0')
                    )
            )
            ->columns([
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('risk_score')
                    ->label('Risk')
                    ->color(fn (AttendanceRecord $record): string => match (true) {
                        $record->risk_score >= 80 => 'danger',
                        $record->risk_score >= 50 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('marked_at')
                    ->dateTime()
                    ->since(),
            ])
            ->paginated(false)
            ->emptyStateHeading('No active session');
    }
}
