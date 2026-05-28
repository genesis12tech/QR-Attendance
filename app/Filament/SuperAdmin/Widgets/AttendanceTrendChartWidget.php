<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use Filament\Widgets\ChartWidget;

class AttendanceTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Attendance Trend (Last 7 Days)';

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn ($d) => now()->subDays($d)->format('D, M j'));
        $counts = collect(range(6, 0))->map(fn ($d) => AttendanceRecord::whereDate('marked_at', now()->subDays($d))
            ->where('status', AttendanceStatus::Present)
            ->count()
        );

        return [
            'datasets' => [
                [
                    'label' => 'Present',
                    'data' => $counts->values()->toArray(),
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $days->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
