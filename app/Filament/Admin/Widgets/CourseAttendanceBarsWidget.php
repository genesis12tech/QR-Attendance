<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use Filament\Widgets\ChartWidget;

class CourseAttendanceBarsWidget extends ChartWidget
{
    protected ?string $heading = 'Course Attendance';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        $courses = Course::when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->withCount(['attendanceSessions as closed_sessions_count' => fn ($q) => $q->where('status', SessionStatus::Closed)])
            ->get();

        $labels = $courses->pluck('code')->toArray();
        $percentages = $courses->map(function (Course $course) {
            if ($course->closed_sessions_count === 0) {
                return 0;
            }

            $attended = AttendanceRecord::whereHas(
                'session',
                fn ($q) => $q->where('course_id', $course->id)->where('status', SessionStatus::Closed)
            )
                ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
                ->count();

            $total = AttendanceSession::where('course_id', $course->id)
                ->where('status', SessionStatus::Closed)
                ->count() * max($course->enrollments()->count(), 1);

            return $total > 0 ? round($attended / $total * 100, 1) : 0;
        })->toArray();

        $backgroundColors = $courses->map(function (Course $course) use ($percentages) {
            $pct = $percentages[array_search($course->code, array_values($courses->pluck('code')->toArray()))];

            return $pct < $course->min_attendance_pct ? 'rgba(239,68,68,0.7)' : 'rgba(16,185,129,0.7)';
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Attendance %',
                    'data' => $percentages,
                    'backgroundColor' => $backgroundColors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
