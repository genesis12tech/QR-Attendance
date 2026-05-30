<?php

namespace App\Filament\Faculty\Widgets;

use App\Enums\ReviewStatus;
use App\Models\AttendanceSession;
use App\Models\ProxyFlag;
use App\Models\Timetable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FacultyStatsOverviewWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $facultyId = auth()->user()?->faculty?->id;

        return [
            Stat::make("Today's Sessions", Cache::remember("faculty_stat.today_sessions.{$facultyId}", 60, function () use ($facultyId) {
                return AttendanceSession::where('faculty_id', $facultyId)
                    ->whereDate('started_at', today())
                    ->count();
            }))
                ->color('info'),

            Stat::make('7-Day Avg Attendance', Cache::remember("faculty_stat.avg_attendance.{$facultyId}", 60, function () use ($facultyId) {
                $row = DB::table('attendance_records as ar')
                    ->join('attendance_sessions as s', 's.id', '=', 'ar.session_id')
                    ->where('s.faculty_id', $facultyId)
                    ->where('s.started_at', '>=', Carbon::now()->subDays(7))
                    ->selectRaw("COUNT(*) as total, SUM(CASE WHEN ar.status IN ('present', 'late') THEN 1 ELSE 0 END) as present_count")
                    ->first();

                $total = (int) ($row->total ?? 0);
                $present = (int) ($row->present_count ?? 0);

                if ($total === 0) {
                    return '—';
                }

                return round(($present / $total) * 100, 1).'%';
            }))
                ->color('success'),

            Stat::make('Open Proxy Flags', Cache::remember("faculty_stat.proxy_flags.{$facultyId}", 60, function () use ($facultyId) {
                return ProxyFlag::where('review_status', ReviewStatus::Pending)
                    ->whereHas(
                        'attendanceRecord.session',
                        fn ($q) => $q->where('faculty_id', $facultyId)
                    )
                    ->count();
            }))
                ->color('warning'),

            Stat::make('My Courses', Cache::remember("faculty_stat.courses.{$facultyId}", 60, function () use ($facultyId) {
                return Timetable::where('faculty_id', $facultyId)
                    ->distinct('course_id')
                    ->count('course_id');
            }))
                ->color('primary'),
        ];
    }
}
