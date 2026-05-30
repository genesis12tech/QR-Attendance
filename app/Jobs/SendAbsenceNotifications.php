<?php

namespace App\Jobs;

use App\Mail\AbsenceNotificationMail;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAbsenceNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 10];

    /**
     * @param  array<int, int>  $studentIds
     */
    public function __construct(
        public readonly array $studentIds,
        public readonly int $courseId,
    ) {}

    public function handle(): void
    {
        $course = Course::findOrFail($this->courseId);

        Student::with('user')
            ->whereIn('id', $this->studentIds)
            ->get()
            ->each(function (Student $student) use ($course): void {
                if ($student->user?->email) {
                    Mail::to($student->user->email)
                        ->send(new AbsenceNotificationMail($student, $course));
                }
            });
    }

    public function failed(\Throwable $exception): void {}
}
