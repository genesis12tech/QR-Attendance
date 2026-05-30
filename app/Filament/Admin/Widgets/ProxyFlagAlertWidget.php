<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\ReviewStatus;
use App\Models\AuditLog;
use App\Models\ProxyFlag;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class ProxyFlagAlertWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.proxy-flag-alert-widget';

    protected ?string $pollingInterval = '10s';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        $flags = ProxyFlag::with(['attendanceRecord.student.user', 'attendanceRecord.session.course'])
            ->where('review_status', ReviewStatus::Pending)
            ->when(
                $departmentId,
                fn ($q) => $q->whereHas(
                    'attendanceRecord.session.course',
                    fn ($c) => $c->where('department_id', $departmentId)
                )
            )
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->limit(5)
            ->get();

        return ['flags' => $flags];
    }

    public function approve(int $flagId): void
    {
        $flag = ProxyFlag::findOrFail($flagId);
        $old = $flag->only('review_status');
        $flag->update(['review_status' => ReviewStatus::Approved->value, 'reviewer_id' => auth()->id(), 'reviewed_at' => now()]);
        AuditLog::record('proxy_flag.approved', $flag, $old, $flag->fresh()->only('review_status'));

        Notification::make()->title('Flag approved')->success()->send();
    }

    public function reject(int $flagId): void
    {
        $flag = ProxyFlag::findOrFail($flagId);
        $old = $flag->only('review_status');
        $flag->update(['review_status' => ReviewStatus::Rejected->value, 'reviewer_id' => auth()->id(), 'reviewed_at' => now()]);
        AuditLog::record('proxy_flag.rejected', $flag, $old, $flag->fresh()->only('review_status'));

        Notification::make()->title('Flag rejected')->success()->send();
    }
}
