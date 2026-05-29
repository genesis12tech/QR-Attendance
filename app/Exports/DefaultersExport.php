<?php

namespace App\Exports;

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DefaultersExport implements FromCollection, WithHeadings
{
    /**
     * @param  array<int, int>  $enrollmentIds
     */
    public function __construct(private readonly array $enrollmentIds) {}

    public function collection(): Collection
    {
        return Enrollment::whereIn('id', $this->enrollmentIds)
            ->with(['student.user', 'course'])
            ->get()
            ->map(function (Enrollment $enrollment) {
                $total = AttendanceSession::where('course_id', $enrollment->course_id)
                    ->where('status', SessionStatus::Closed->value)
                    ->count();

                $attended = $total > 0
                    ? AttendanceRecord::where('student_id', $enrollment->student_id)
                        ->whereHas('session', fn ($q) => $q->where('course_id', $enrollment->course_id))
                        ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
                        ->count()
                    : 0;

                $pct = $total > 0 ? round($attended / $total * 100, 1) : 0;

                return [
                    'student' => $enrollment->student->user->name ?? '',
                    'course' => $enrollment->course->code ?? '',
                    'attended_total' => "{$attended}/{$total}",
                    'attendance_pct' => $pct.'%',
                    'minimum_pct' => $enrollment->course->min_attendance_pct.'%',
                ];
            });
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Student', 'Course', 'Attended / Total', 'Attendance %', 'Minimum %'];
    }
}
