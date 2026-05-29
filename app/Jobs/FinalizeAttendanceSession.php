<?php

namespace App\Jobs;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class FinalizeAttendanceSession implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly AttendanceSession $session) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $session = $this->session;
            $oldStatus = $session->status;

            $enrolledStudentIds = Enrollment::where('course_id', $session->course_id)
                ->where('class_group_id', $session->class_group_id)
                ->where('status', EnrollmentStatus::Active)
                ->pluck('student_id');

            $presentStudentIds = AttendanceRecord::where('session_id', $session->id)
                ->whereIn('student_id', $enrolledStudentIds)
                ->pluck('student_id');

            $absentStudentIds = $enrolledStudentIds->diff($presentStudentIds);

            foreach ($absentStudentIds as $studentId) {
                AttendanceRecord::create([
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                    'status' => AttendanceStatus::Absent,
                    'marked_at' => now(),
                    'risk_score' => 0,
                ]);
            }

            $totals = AttendanceRecord::where('session_id', $session->id)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $session->update([
                'status' => SessionStatus::Closed,
                'closed_at' => now(),
                'total_present' => $totals[AttendanceStatus::Present->value] ?? 0,
                'total_late' => $totals[AttendanceStatus::Late->value] ?? 0,
                'total_absent' => $totals[AttendanceStatus::Absent->value] ?? 0,
            ]);

            AuditLog::record(
                action: 'session.closed',
                entity: $session,
                oldValues: ['status' => $oldStatus->value],
                newValues: ['status' => SessionStatus::Closed->value],
            );
        });
    }
}
