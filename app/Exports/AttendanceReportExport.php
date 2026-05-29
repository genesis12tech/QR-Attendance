<?php

namespace App\Exports;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceReportExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Student', 'Roll No', 'Course', 'Session Date', 'Status', 'Marked At', 'Risk Score'];
    }

    /** @param AttendanceRecord $row */
    public function map($row): array
    {
        return [
            $row->student?->user?->name ?? '—',
            $row->student?->roll_no ?? '—',
            $row->session?->course?->code ?? '—',
            $row->session?->started_at?->format('Y-m-d') ?? '—',
            $row->status instanceof AttendanceStatus ? $row->status->value : (string) $row->status,
            $row->marked_at?->format('Y-m-d H:i') ?? '—',
            $row->risk_score,
        ];
    }
}
