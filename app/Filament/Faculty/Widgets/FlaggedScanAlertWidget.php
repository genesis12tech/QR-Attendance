<?php

namespace App\Filament\Faculty\Widgets;

use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ProxyFlag;
use App\Models\SystemSetting;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class FlaggedScanAlertWidget extends Widget
{
    protected string $view = 'filament.faculty.widgets.flagged-scan-alert-widget';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $facultyId = auth()->user()?->faculty?->id;

        $activeSession = AttendanceSession::where('faculty_id', $facultyId)
            ->where('status', SessionStatus::Active)
            ->latest()
            ->first();

        if (! $activeSession) {
            return [
                'flags' => collect(),
                'canReview' => false,
            ];
        }

        $flags = ProxyFlag::with(['attendanceRecord.student.user'])
            ->where('review_status', ReviewStatus::Pending)
            ->whereHas(
                'attendanceRecord',
                fn ($q) => $q->where('session_id', $activeSession->id)
            )
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->get();

        $canReview = SystemSetting::get('faculty_can_review_flags') === 'true';

        return [
            'flags' => $flags,
            'canReview' => $canReview,
        ];
    }

    public function allow(int $flagId): void
    {
        $flag = ProxyFlag::findOrFail($flagId);
        $old = $flag->only('review_status');
        $flag->update([
            'review_status' => ReviewStatus::Approved->value,
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        AuditLog::record('proxy_flag.allowed_by_faculty', $flag, $old, $flag->fresh()->only('review_status'));

        Notification::make()->title('Flag allowed')->success()->send();
    }

    public function deny(int $flagId): void
    {
        $flag = ProxyFlag::findOrFail($flagId);
        $old = $flag->only('review_status');
        $flag->update([
            'review_status' => ReviewStatus::Rejected->value,
            'reviewer_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        AuditLog::record('proxy_flag.denied_by_faculty', $flag, $old, $flag->fresh()->only('review_status'));

        Notification::make()->title('Flag denied')->success()->send();
    }
}
