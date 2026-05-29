<?php

namespace App\Filament\Admin\Resources\ProxyFlags\Tables;

use App\Enums\ProxySeverity;
use App\Enums\ReviewStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\ProxyFlag;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class ProxyFlagTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendanceRecord.student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('attendanceRecord.session.course.code')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('severity')
                    ->badge()
                    ->sortable(),
                TextColumn::make('reason_code')
                    ->label('Reason')
                    ->sortable(),
                TextColumn::make('risk_score')
                    ->label('Risk Score')
                    ->badge()
                    ->color(fn (ProxyFlag $record): string => match (true) {
                        $record->risk_score >= 80 => 'danger',
                        $record->risk_score >= 50 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('review_status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Flagged At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options(ProxySeverity::class),
                SelectFilter::make('review_status')
                    ->options(ReviewStatus::class),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']))
                    ),
                Filter::make('course')
                    ->form([
                        Select::make('course_id')
                            ->label('Course')
                            ->options(fn () => Course::orderBy('code')->pluck('code', 'id'))
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['course_id'], fn ($q) => $q->whereHas(
                            'attendanceRecord.session',
                            fn ($q) => $q->where('course_id', $data['course_id'])
                        ))
                    ),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ProxyFlag $record): bool => $record->review_status === ReviewStatus::Pending)
                    ->schema([
                        Textarea::make('reviewer_notes')
                            ->label('Notes (optional)')
                            ->rows(3),
                    ])
                    ->action(function (ProxyFlag $record, array $data): void {
                        $old = $record->only(['review_status', 'reviewer_notes']);
                        $record->update([
                            'review_status' => ReviewStatus::Approved,
                            'reviewer_id' => auth()->id(),
                            'reviewer_notes' => $data['reviewer_notes'] ?? null,
                            'reviewed_at' => now(),
                        ]);
                        AuditLog::record('proxy_flag.approved', $record, $old, [
                            'review_status' => ReviewStatus::Approved->value,
                            'reviewer_notes' => $data['reviewer_notes'] ?? null,
                        ]);
                        Notification::make()->title('Proxy flag approved')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (ProxyFlag $record): bool => $record->review_status === ReviewStatus::Pending)
                    ->schema([
                        Textarea::make('reviewer_notes')
                            ->label('Reason (required)')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (ProxyFlag $record, array $data): void {
                        $old = $record->only(['review_status', 'reviewer_notes']);
                        $record->update([
                            'review_status' => ReviewStatus::Rejected,
                            'reviewer_id' => auth()->id(),
                            'reviewer_notes' => $data['reviewer_notes'],
                            'reviewed_at' => now(),
                        ]);
                        AuditLog::record('proxy_flag.rejected', $record, $old, [
                            'review_status' => ReviewStatus::Rejected->value,
                            'reviewer_notes' => $data['reviewer_notes'],
                        ]);
                        Notification::make()->title('Proxy flag rejected')->success()->send();
                    }),
                Action::make('view_evidence')
                    ->label('Evidence')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->modalContent(fn (ProxyFlag $record) => new HtmlString(
                        self::renderEvidenceHtml($record)
                    ))
                    ->modalSubmitAction(false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_approve')
                        ->label('Approve Selected')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->schema([
                            Textarea::make('reviewer_notes')
                                ->label('Notes (optional)')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (ProxyFlag $flag) => $flag->update([
                                'review_status' => ReviewStatus::Approved,
                                'reviewer_id' => auth()->id(),
                                'reviewer_notes' => $data['reviewer_notes'] ?? null,
                                'reviewed_at' => now(),
                            ]));
                            Notification::make()->title('Proxy flags approved')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulk_reject')
                        ->label('Reject Selected')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->schema([
                            Textarea::make('reviewer_notes')
                                ->label('Reason (required)')
                                ->rows(3)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (ProxyFlag $flag) => $flag->update([
                                'review_status' => ReviewStatus::Rejected,
                                'reviewer_id' => auth()->id(),
                                'reviewer_notes' => $data['reviewer_notes'],
                                'reviewed_at' => now(),
                            ]));
                            Notification::make()->title('Proxy flags rejected')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No proxy flags found')
            ->emptyStateDescription('Proxy flags are generated automatically when suspicious attendance scans are detected.');
    }

    private static function renderEvidenceHtml(ProxyFlag $flag): string
    {
        $evidence = $flag->evidence_json ?? [];
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
