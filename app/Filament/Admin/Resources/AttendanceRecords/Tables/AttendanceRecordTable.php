<?php

namespace App\Filament\Admin\Resources\AttendanceRecords\Tables;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Course;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AttendanceRecordTable
{
    public static function configure(Table $table): Table
    {
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
                TextColumn::make('session.course.code')
                    ->label('Course')
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
                TextColumn::make('overriddenBy.name')
                    ->label('Overridden By')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::class),
                Filter::make('course')
                    ->form([
                        Select::make('course_id')
                            ->label('Course')
                            ->options(fn () => Course::orderBy('code')->pluck('code', 'id'))
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['course_id'], fn ($q) => $q->whereHas(
                            'session',
                            fn ($q) => $q->where('course_id', $data['course_id'])
                        ))
                    ),
                Filter::make('marked_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn ($q) => $q->whereDate('marked_at', '>=', $data['from']))
                        ->when($data['until'], fn ($q) => $q->whereDate('marked_at', '<=', $data['until']))
                    ),
                Filter::make('high_risk')
                    ->label('High Risk (≥ 50)')
                    ->query(fn (Builder $query) => $query->where('risk_score', '>=', 50)),
            ])
            ->recordActions([
                Action::make('override')
                    ->label('Override')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->schema([
                        Select::make('status')
                            ->options(AttendanceStatus::class)
                            ->required(),
                        Textarea::make('override_reason')
                            ->label('Override Reason (min 20 characters)')
                            ->rows(3)
                            ->minLength(20)
                            ->required(),
                    ])
                    ->action(function (AttendanceRecord $record, array $data): void {
                        $old = $record->only(['status', 'override_reason', 'override_by']);
                        $record->update([
                            'status' => $data['status'],
                            'override_reason' => $data['override_reason'],
                            'override_by' => auth()->id(),
                            'overridden_at' => now(),
                        ]);
                        AuditLog::record('attendance_record.overridden', $record, $old, [
                            'status' => $data['status'],
                            'override_reason' => $data['override_reason'],
                            'override_by' => auth()->id(),
                        ]);
                        Notification::make()->title('Attendance record overridden')->success()->send();
                    }),
                Action::make('view_evidence')
                    ->label('Evidence')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->modalContent(fn (AttendanceRecord $record) => new HtmlString(
                        self::renderEvidenceHtml($record)
                    ))
                    ->modalSubmitAction(false),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No attendance records found');
    }

    private static function renderEvidenceHtml(AttendanceRecord $record): string
    {
        $evidence = $record->evidence_json ?? [];
        $html = '<div class="space-y-3 text-sm">';

        if (isset($evidence['latitude'], $evidence['longitude'])) {
            $html .= '<p class="font-semibold">GPS Location</p>';
            $html .= '<p>Lat: '.e($evidence['latitude']).', Lng: '.e($evidence['longitude']).'</p>';
        }

        if (isset($evidence['device'])) {
            $html .= '<p class="font-semibold mt-2">Device Info</p>';
            $html .= '<pre class="text-xs bg-gray-50 p-2 rounded">'.e(json_encode($evidence['device'], JSON_PRETTY_PRINT)).'</pre>';
        }

        $riskKeys = ['risk_score', 'clock_skew', 'distance_m', 'device_match'];
        $riskData = array_intersect_key($evidence, array_flip($riskKeys));

        if (! empty($riskData)) {
            $html .= '<p class="font-semibold mt-2">Risk Breakdown</p>';
            $html .= '<pre class="text-xs bg-gray-50 p-2 rounded">'.e(json_encode($riskData, JSON_PRETTY_PRINT)).'</pre>';
        }

        if (empty($evidence)) {
            $html .= '<p class="text-gray-500">No evidence data available.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
