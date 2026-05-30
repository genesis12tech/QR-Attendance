<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\ProxyFlag;
use App\Models\Student;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AdminStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        return [
            Stat::make('Students', Cache::remember("admin_stat.students.{$departmentId}", 60, function () use ($departmentId) {
                return Student::when($departmentId, fn ($q) => $q->where('department_id', $departmentId))->count();
            }))
                ->color('success'),

            Stat::make('Active Sessions', Cache::remember("admin_stat.active_sessions.{$departmentId}", 60, function () use ($departmentId) {
                return AttendanceSession::where('status', SessionStatus::Active)
                    ->when($departmentId, fn ($q) => $q->whereHas('course', fn ($c) => $c->where('department_id', $departmentId)))
                    ->count();
            }))
                ->color('info'),

            Stat::make('Pending Proxy Flags', Cache::remember("admin_stat.proxy_flags.{$departmentId}", 60, function () use ($departmentId) {
                return ProxyFlag::where('review_status', ReviewStatus::Pending)
                    ->when($departmentId, fn ($q) => $q->whereHas(
                        'attendanceRecord.session.course',
                        fn ($c) => $c->where('department_id', $departmentId)
                    ))
                    ->count();
            }))
                ->color('danger'),

            Stat::make('Defaulters', Cache::remember("admin_stat.defaulters.{$departmentId}", 60, function () {
                return 0;
            }))
                ->color('warning'),
        ];
    }
}
