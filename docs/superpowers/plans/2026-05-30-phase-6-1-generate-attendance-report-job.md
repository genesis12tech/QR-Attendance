# Phase 6.1 — GenerateAttendanceReport Job Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the `GenerateAttendanceReport` queued job that generates PDF/CSV/XLSX attendance reports, stores them to disk, and notifies the requesting user on completion or failure.

**Architecture:** A single queued job accepts report type + filters + format, builds an Eloquent query, delegates file generation to a dedicated Excel export class (maatwebsite/excel) or a Blade/dompdf PDF renderer, then persists a `SessionExport` record and sends a Filament database notification. `session_exports.session_id` is made nullable first to support non-session-scoped reports.

**Tech Stack:** Laravel 12, `barryvdh/laravel-dompdf ^3.0`, `maatwebsite/excel ^3.1`, Filament v4 notifications, PHP 8.4 (`/Users/thomas/.config/herd-lite/bin/php`)

---

## File Map

| Action | Path |
|---|---|
| Create migration | `database/migrations/2026_05_30_000001_make_session_id_nullable_on_session_exports.php` |
| Create | `app/Jobs/GenerateAttendanceReport.php` |
| Create | `app/Exports/AttendanceReportExport.php` |
| Create | `resources/views/reports/attendance-report.blade.php` |
| Create | `tests/Feature/Jobs/GenerateAttendanceReportTest.php` |

---

## Task 1: Make session_id Nullable on session_exports

**Files:**
- Create: `database/migrations/2026_05_30_000001_make_session_id_nullable_on_session_exports.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_exports', function (Blueprint $table) {
            $table->foreignId('session_id')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('session_exports', function (Blueprint $table) {
            $table->foreignId('session_id')
                ->nullable(false)
                ->change();
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan migrate
```

Expected: `2026_05_30_000001_make_session_id_nullable_on_session_exports .... DONE`

- [ ] **Step 3: Run pint**

```bash
vendor/bin/pint database/migrations/2026_05_30_000001_make_session_id_nullable_on_session_exports.php --format agent
```

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_30_000001_make_session_id_nullable_on_session_exports.php
git commit -m "feat(migration): make session_exports.session_id nullable for general reports"
```

---

## Task 2: Write All 5 Failing Tests

**Files:**
- Create: `tests/Feature/Jobs/GenerateAttendanceReportTest.php`

- [ ] **Step 1: Create the test file**

```php
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
```

- [ ] **Step 2: Run the tests to confirm they all fail**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=GenerateAttendanceReport
```

Expected: 5 failures — `Class "App\Jobs\GenerateAttendanceReport" not found` or similar.

- [ ] **Step 3: Commit the failing tests**

```bash
git add tests/Feature/Jobs/GenerateAttendanceReportTest.php
git commit -m "test: add failing tests for GenerateAttendanceReport job (Phase 6.1)"
```

---

## Task 3: AttendanceReportExport Class (Excel)

**Files:**
- Create: `app/Exports/AttendanceReportExport.php`

- [ ] **Step 1: Create the export class**

```php
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
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Exports/AttendanceReportExport.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Exports/AttendanceReportExport.php
git commit -m "feat: add AttendanceReportExport class for CSV/XLSX generation"
```

---

## Task 4: PDF Blade View

**Files:**
- Create: `resources/views/reports/attendance-report.blade.php`

- [ ] **Step 1: Create the directory and view**

```bash
mkdir -p resources/views/reports
```

Create `resources/views/reports/attendance-report.blade.php`:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
        h1 { font-size: 15px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #f0f0f0; font-weight: bold; text-align: left; padding: 5px 7px; border: 1px solid #ccc; font-size: 10px; }
        td { padding: 4px 7px; border: 1px solid #ddd; vertical-align: top; }
        tr:nth-child(even) td { background: #fafafa; }
        .footer { margin-top: 20px; font-size: 9px; color: #999; border-top: 1px solid #ddd; padding-top: 6px; }
        .empty { text-align: center; color: #999; padding: 16px; }
    </style>
</head>
<body>
    <h1>Attendance Report</h1>
    <div class="meta">
        Type: {{ ucfirst(str_replace('_', ' ', $type)) }}
        &nbsp;|&nbsp;
        Period: {{ $from }} to {{ $to }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Student</th>
                <th>Roll No</th>
                <th>Course</th>
                <th>Session Date</th>
                <th>Status</th>
                <th>Marked At</th>
                <th>Risk Score</th>
            </tr>
        </thead>
        <tbody>
            @forelse($records as $record)
            <tr>
                <td>{{ $record->student?->user?->name ?? '—' }}</td>
                <td>{{ $record->student?->roll_no ?? '—' }}</td>
                <td>{{ $record->session?->course?->code ?? '—' }}</td>
                <td>{{ $record->session?->started_at?->format('Y-m-d') ?? '—' }}</td>
                <td>{{ $record->status instanceof \App\Enums\AttendanceStatus ? $record->status->value : $record->status }}</td>
                <td>{{ $record->marked_at?->format('Y-m-d H:i') ?? '—' }}</td>
                <td>{{ $record->risk_score }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="empty">No records found for this period.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Generated by QR Attendance System on {{ $generatedAt }}
    </div>
</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/reports/attendance-report.blade.php
git commit -m "feat: add PDF blade view for attendance reports"
```

---

## Task 5: GenerateAttendanceReport Job — Core handle() (Makes Tests 1, 2, 4, 5 Pass)

**Files:**
- Create: `app/Jobs/GenerateAttendanceReport.php`

- [ ] **Step 1: Create the job**

```php
<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Enums\SessionStatus;
use App\Exports\AttendanceReportExport;
use App\Models\AttendanceRecord;
use App\Models\SessionExport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Actions\Action as NotificationAction;
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

    public int $tries = 3;

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
        $export = SessionExport::where('requested_by', $this->requestedBy)
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
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint app/Jobs/GenerateAttendanceReport.php --format agent
```

- [ ] **Step 3: Run all 5 tests to verify they pass**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=GenerateAttendanceReport
```

Expected: all 5 pass. If any fail:
- **FK constraint on `session_id`** in the `failed()` test: the nullable migration from Task 1 may not have run — check with `php artisan migrate:status | grep nullable` and re-run `php artisan migrate` if needed.
- **`Storage::disk('local')->assertExists` fails**: confirm `Storage::fake('local')` is in `beforeEach` and that `Excel::store(..., 'local')` matches the disk name.
- **PDF test fails with a view error**: run `/Users/thomas/.config/herd-lite/bin/php artisan view:cache` to surface Blade syntax errors.

- [ ] **Step 4: Commit**

```bash
git add app/Jobs/GenerateAttendanceReport.php
git commit -m "feat: implement GenerateAttendanceReport queued job (Phase 6.1)"
```

---

## Task 6: Verify All 5 Tests Pass

- [ ] **Step 1: Run just the job tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=GenerateAttendanceReport
```

Expected:
```
✓ creates a session_export record on completion
✓ sets status to ready with file_path and expires_at
✓ sets status to failed when failed() is called
✓ generates a pdf file for pdf format
✓ generates an xlsx file for xlsx format

Tests: 5 passed
```

If any test fails, diagnose:
- **`session_id` constraint error** on the `failed()` test: confirm migration ran — `php artisan migrate:status | grep nullable`
- **`Storage::disk('local')->assertExists` fails**: confirm `Storage::fake('local')` is in `beforeEach` and that `Excel::store(..., 'local')` uses the same disk name
- **PDF test fails**: check that `resources/views/reports/attendance-report.blade.php` exists and has no syntax errors — run `php artisan view:cache` to surface errors

- [ ] **Step 2: Run the full test suite**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
```

Expected: all existing tests still pass (207+5 = 212 passed, 0 failed).

- [ ] **Step 3: Run pint across all new files**

```bash
vendor/bin/pint app/Jobs/GenerateAttendanceReport.php app/Exports/AttendanceReportExport.php tests/Feature/Jobs/GenerateAttendanceReportTest.php --format agent
```

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "feat: Phase 6.1 GenerateAttendanceReport job complete — 5/5 tests passing"
```
