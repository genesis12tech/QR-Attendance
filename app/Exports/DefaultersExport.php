<?php

namespace App\Exports;

use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            ->addSelect([
                'enrollments.*',
                DB::raw('(
                    SELECT COUNT(*)
                    FROM attendance_sessions
                    WHERE attendance_sessions.course_id = enrollments.course_id
                    AND attendance_sessions.status = "closed"
                ) AS total_sessions'),
                DB::raw('(
                    SELECT COUNT(*)
                    FROM attendance_records
                    INNER JOIN attendance_sessions ON attendance_records.attendance_session_id = attendance_sessions.id
                    WHERE attendance_records.student_id = enrollments.student_id
                    AND attendance_sessions.course_id = enrollments.course_id
                    AND attendance_sessions.status = "closed"
                    AND attendance_records.status IN ("present", "late")
                ) AS attended_sessions'),
            ])
            ->get()
            ->map(function (Enrollment $enrollment) {
                $total = (int) $enrollment->total_sessions;
                $attended = (int) $enrollment->attended_sessions;
                $pct = $total > 0 ? round($attended / $total * 100, 1) : 0;

                return [
                    'student' => $enrollment->student->user->name ?? '',
                    'course' => $enrollment->course->code ?? '',
                    'attended_total' => "{$attended}/{$total}",
                    'attendance_pct' => $pct.'%',
                    'minimum_pct' => number_format((float) $enrollment->course->min_attendance_pct, 1).'%',
                ];
            });
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Student', 'Course', 'Attended / Total', 'Attendance %', 'Minimum %'];
    }
}
