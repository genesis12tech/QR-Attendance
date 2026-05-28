<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\ProxyFlag;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class SuperAdminStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', Cache::remember('stat.total_users', 60, fn () => User::count()))
                ->color('violet'),
            Stat::make('Active Sessions', Cache::remember('stat.active_sessions', 60, fn () => AttendanceSession::where('status', SessionStatus::Active)->count()))
                ->color('success'),
            Stat::make('Open Proxy Flags', Cache::remember('stat.open_proxy_flags', 60, fn () => ProxyFlag::where('review_status', ReviewStatus::Pending)->count()))
                ->color('warning'),
            Stat::make('Departments', Cache::remember('stat.departments', 60, fn () => Department::count()))
                ->color('info'),
        ];
    }
}
