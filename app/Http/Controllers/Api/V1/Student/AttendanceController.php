<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        abort_unless($student !== null, 403, 'No student profile associated with this account.');

        $records = AttendanceRecord::where('student_id', $student->id)
            ->with(['session.course'])
            ->orderByDesc('marked_at')
            ->paginate(15);

        $mapped = $records->through(fn ($record) => [
            'id' => $record->id,
            'status' => $record->status->value,
            'marked_at' => $record->marked_at?->toISOString(),
            'risk_score' => $record->risk_score,
            'session' => [
                'started_at' => $record->session?->started_at?->toISOString(),
                'course_code' => $record->session?->course?->code,
                'course_name' => $record->session?->course?->name,
            ],
        ]);

        return response()->json([
            'data' => $mapped->items(),
            'meta' => [
                'current_page' => $mapped->currentPage(),
                'per_page' => $mapped->perPage(),
                'total' => $mapped->total(),
                'last_page' => $mapped->lastPage(),
            ],
            'links' => [
                'first' => $mapped->url(1),
                'last' => $mapped->url($mapped->lastPage()),
                'prev' => $mapped->previousPageUrl(),
                'next' => $mapped->nextPageUrl(),
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $student = $request->user()->student;
        abort_unless($student !== null, 403, 'No student profile associated with this account.');

        $enrollments = Enrollment::where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Active)
            ->with('course')
            ->addSelect([
                'enrollments.*',
                DB::raw('(
                    SELECT COUNT(*)
                    FROM attendance_records
                    INNER JOIN attendance_sessions
                        ON attendance_records.session_id = attendance_sessions.id
                    WHERE attendance_records.student_id = enrollments.student_id
                      AND attendance_sessions.course_id = enrollments.course_id
                ) AS total_sessions'),
                DB::raw("(
                    SELECT COUNT(*)
                    FROM attendance_records
                    INNER JOIN attendance_sessions
                        ON attendance_records.session_id = attendance_sessions.id
                    WHERE attendance_records.student_id = enrollments.student_id
                      AND attendance_sessions.course_id = enrollments.course_id
                      AND attendance_records.status IN ('present', 'late')
                ) AS attended_sessions"),
            ])
            ->get();

        $summary = $enrollments->map(function (Enrollment $enrollment) {
            $total = (int) $enrollment->total_sessions;
            $attended = (int) $enrollment->attended_sessions;
            $percentage = $total > 0 ? round($attended / $total * 100, 2) : 0.0;
            $minimumPct = (float) $enrollment->course->min_attendance_pct;

            return [
                'course_code' => $enrollment->course->code,
                'course_name' => $enrollment->course->name,
                'attended' => $attended,
                'total' => $total,
                'percentage' => $percentage,
                'minimum_pct' => $enrollment->course->min_attendance_pct,
                'below_minimum' => $percentage < $minimumPct,
            ];
        });

        return response()->json($summary);
    }
}
