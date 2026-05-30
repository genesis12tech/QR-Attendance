<?php

namespace App\Filament\Admin\Widgets;

use App\Models\AttendanceSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ActiveSessionsTableWidget extends TableWidget
{
    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        return $table
            ->heading('Today\'s Sessions')
            ->query(
                AttendanceSession::query()
                    ->with(['course', 'faculty.user', 'room'])
                    ->whereDate('started_at', today())
                    ->when(
                        $departmentId,
                        fn (Builder $q) => $q->whereHas(
                            'course',
                            fn (Builder $c) => $c->where('department_id', $departmentId)
                        )
                    )
                    ->latest('started_at')
            )
            ->columns([
                TextColumn::make('course.name')
                    ->label('Course')
                    ->searchable(),
                TextColumn::make('faculty.user.name')
                    ->label('Faculty'),
                TextColumn::make('room.name')
                    ->label('Room'),
                TextColumn::make('present_enrolled')
                    ->label('Present / Enrolled')
                    ->state(fn (AttendanceSession $record): string => "{$record->total_present} / {$record->total_enrolled}"),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->emptyStateHeading('No sessions today');
    }
}
