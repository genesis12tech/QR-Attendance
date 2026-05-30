<?php

namespace App\Filament\Faculty\Resources\ProxyFlags\Tables;

use App\Enums\ReviewStatus;
use App\Models\AuditLog;
use App\Models\ProxyFlag;
use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
            ])
            ->recordActions([
                Action::make('allow')
                    ->label('Allow')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ProxyFlag $record): bool => SystemSetting::get('faculty_can_review_flags') === 'true'
                        && $record->review_status === ReviewStatus::Pending)
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
                        Notification::make()->title('Proxy flag allowed')->success()->send();
                    }),

                Action::make('deny')
                    ->label('Deny')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (ProxyFlag $record): bool => SystemSetting::get('faculty_can_review_flags') === 'true'
                        && $record->review_status === ReviewStatus::Pending)
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
                        Notification::make()->title('Proxy flag denied')->success()->send();
                    }),

                Action::make('view_evidence')
                    ->label('Evidence')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->modalContent(fn (ProxyFlag $record) => new HtmlString(
                        self::renderEvidenceHtml($record)
                    ))
                    ->modalSubmitAction(false),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No proxy flags found')
            ->emptyStateDescription('Proxy flags are generated automatically when suspicious attendance scans are detected.');
    }

    private static function renderEvidenceHtml(ProxyFlag $flag): string
    {
        $evidence = $flag->evidence_json ?? [];
        $html = '<div class="space-y-3 text-sm">';
        $hasContent = false;

        if (isset($evidence['gps_distance_m'])) {
            $hasContent = true;
            $html .= '<p class="font-semibold">GPS Distance from Room</p>';
            $html .= '<p>'.e(number_format((float) $evidence['gps_distance_m'], 1)).' m</p>';
        }

        if (isset($evidence['device_trusted'])) {
            $hasContent = true;
            $html .= '<p class="font-semibold mt-2">Device</p>';
            $html .= '<p>Trusted: '.($evidence['device_trusted'] ? 'Yes' : 'No').'</p>';
        }

        if (isset($evidence['qr_age_seconds'])) {
            $hasContent = true;
            $html .= '<p class="font-semibold mt-2">QR Age</p>';
            $html .= '<p>'.e($evidence['qr_age_seconds']).' seconds</p>';
        }

        if (isset($evidence['weights'])) {
            $hasContent = true;
            $html .= '<p class="font-semibold mt-2">Risk Weights</p>';
            $html .= '<pre class="text-xs bg-gray-50 p-2 rounded">'.e(json_encode($evidence['weights'], JSON_PRETTY_PRINT)).'</pre>';
        }

        if (! $hasContent) {
            $html .= '<p class="text-gray-500">No evidence data available.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
