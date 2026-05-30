<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->get();

        $summary = $enrollments->map(function (Enrollment $enrollment) use ($student) {
            $total = AttendanceRecord::where('student_id', $student->id)
                ->whereHas('session', fn ($q) => $q->where('course_id', $enrollment->course_id))
                ->count();

            $attended = AttendanceRecord::where('student_id', $student->id)
                ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
                ->whereHas('session', fn ($q) => $q->where('course_id', $enrollment->course_id))
                ->count();

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
