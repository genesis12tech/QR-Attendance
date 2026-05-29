<?php

use App\Enums\ExportStatus;
use App\Jobs\GenerateAttendanceReport;
use App\Models\SessionExport;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

function makeReportJob(string $format = 'pdf', array $overrides = []): GenerateAttendanceReport
{
    $user = User::factory()->admin()->create();

    return new GenerateAttendanceReport(
        type: $overrides['type'] ?? 'date_range',
        departmentId: $overrides['departmentId'] ?? null,
        courseId: $overrides['courseId'] ?? null,
        from: $overrides['from'] ?? now()->subDays(7)->toDateString(),
        to: $overrides['to'] ?? now()->toDateString(),
        format: $format,
        requestedBy: $overrides['requestedBy'] ?? $user->id,
    );
}

it('creates a session_export record on completion', function () {
    $job = makeReportJob('pdf');
    $job->handle();

    expect(SessionExport::count())->toBe(1)
        ->and(SessionExport::first()->status)->toBe(ExportStatus::Ready);
});

it('sets status to ready with file_path and expires_at', function () {
    $job = makeReportJob('pdf');
    $job->handle();

    $export = SessionExport::first();

    expect($export->file_path)->not->toBeNull()
        ->and($export->expires_at)->not->toBeNull()
        ->and($export->expires_at->diffInHours(now(), true))->toBeLessThanOrEqual(25);
});

it('sets status to failed when failed() is called', function () {
    $user = User::factory()->admin()->create();

    $export = SessionExport::factory()->create([
        'session_id' => null,
        'requested_by' => $user->id,
        'status' => ExportStatus::Processing,
    ]);

    $job = new GenerateAttendanceReport(
        type: 'date_range',
        departmentId: null,
        courseId: null,
        from: now()->subWeek()->toDateString(),
        to: now()->toDateString(),
        format: 'pdf',
        requestedBy: $user->id,
    );

    $job->failed(new \Exception('Something went wrong'));

    expect($export->fresh()->status)->toBe(ExportStatus::Failed);
});

it('generates a pdf file for pdf format', function () {
    $job = makeReportJob('pdf');
    $job->handle();

    $export = SessionExport::first();

    Storage::disk('local')->assertExists($export->file_path);
    expect(pathinfo($export->file_path, PATHINFO_EXTENSION))->toBe('pdf');
});

it('generates an xlsx file for xlsx format', function () {
    $job = makeReportJob('xlsx');
    $job->handle();

    $export = SessionExport::first();

    Storage::disk('local')->assertExists($export->file_path);
    expect(pathinfo($export->file_path, PATHINFO_EXTENSION))->toBe('xlsx');
});
