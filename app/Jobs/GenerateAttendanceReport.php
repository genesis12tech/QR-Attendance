<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Enums\SessionStatus;
use App\Exports\AttendanceReportExport;
use App\Models\AttendanceRecord;
use App\Models\SessionExport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class GenerateAttendanceReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?int $exportId = null;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 10];

    public function __construct(
        public readonly string $type,
        public readonly ?int $departmentId,
        public readonly ?int $courseId,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly string $format,
        public readonly int $requestedBy,
    ) {}

    public function handle(): void
    {
        $export = SessionExport::create([
            'session_id' => null,
            'requested_by' => $this->requestedBy,
            'format' => $this->format,
            'status' => ExportStatus::Processing,
        ]);

        $this->exportId = $export->id;

        $query = $this->buildQuery();
        $filename = 'reports/'.Str::uuid().'.'.$this->extension();

        match ($this->format) {
            'pdf' => $this->generatePdf($query, $filename),
            'csv' => Excel::store(
                new AttendanceReportExport($query),
                $filename,
                'local',
                \Maatwebsite\Excel\Excel::CSV,
            ),
            default => Excel::store(new AttendanceReportExport($query), $filename, 'local'),
        };

        $export->update([
            'status' => ExportStatus::Ready,
            'file_path' => $filename,
            'expires_at' => now()->addHours(24),
        ]);

        $user = User::find($this->requestedBy);

        if ($user) {
            Notification::make()
                ->title('Report ready')
                ->body('Your attendance report is ready for download. The link expires in 24 hours.')
                ->actions([
                    NotificationAction::make('download')
                        ->label('Download')
                        ->url(URL::temporarySignedRoute(
                            'session-exports.download',
                            now()->addHours(24),
                            ['export' => $export->id],
                        ))
                        ->button(),
                ])
                ->success()
                ->sendToDatabase($user);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $export = $this->exportId
            ? SessionExport::find($this->exportId)
            : SessionExport::where('requested_by', $this->requestedBy)
                ->where('status', ExportStatus::Processing)
                ->latest()
                ->first();

        if ($export) {
            $export->update(['status' => ExportStatus::Failed]);
        }

        $user = User::find($this->requestedBy);

        if ($user) {
            Notification::make()
                ->title('Report failed')
                ->body('Your attendance report could not be generated. Please try again.')
                ->warning()
                ->sendToDatabase($user);
        }
    }

    private function buildQuery(): Builder
    {
        $query = AttendanceRecord::query()
            ->with(['student.user', 'session.course'])
            ->whereHas('session', function (Builder $q) {
                $q->where('status', SessionStatus::Closed);

                if ($this->from && $this->to) {
                    $q->whereBetween('started_at', [
                        $this->from.' 00:00:00',
                        $this->to.' 23:59:59',
                    ]);
                }
            });

        match ($this->type) {
            'department' => $this->departmentId
                ? $query->whereHas('session.course', fn (Builder $q) => $q->where('department_id', $this->departmentId))
                : null,
            'course' => $this->courseId
                ? $query->whereHas('session', fn (Builder $q) => $q->where('course_id', $this->courseId))
                : null,
            'faculty' => $this->departmentId
                ? $query->whereHas('session', fn (Builder $q) => $q->where('faculty_id', $this->departmentId))
                : null,
            'student' => $this->courseId
                ? $query->where('student_id', $this->courseId)
                : null,
            default => null,
        };

        return $query;
    }

    private function generatePdf(Builder $query, string $filename): void
    {
        $records = $query->get();

        $pdf = Pdf::loadView('reports.attendance-report', [
            'records' => $records,
            'type' => $this->type,
            'from' => $this->from ?? '—',
            'to' => $this->to ?? '—',
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ]);

        Storage::disk('local')->put($filename, $pdf->output());
    }

    private function extension(): string
    {
        return match ($this->format) {
            'pdf' => 'pdf',
            'csv' => 'csv',
            default => 'xlsx',
        };
    }
}
