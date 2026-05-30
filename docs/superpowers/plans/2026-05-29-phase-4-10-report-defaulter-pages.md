# Phase 4.10 — ReportPage & DefaulterListPage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build two Admin panel pages: ReportPage (form to queue attendance reports) and DefaulterListPage (cached table of students below the minimum attendance threshold, with notify and export actions).

**Architecture:** Both pages are Filament custom `Page` classes living in `app/Filament/Admin/Pages/`. ReportPage holds a form that dispatches `GenerateAttendanceReport` on submit. DefaulterListPage uses Filament's `HasTable` contract; the defaulter computation is cached for 5 minutes under a department-scoped key, and the table queries `enrollments` filtered to those IDs with subqueries for computed columns. A separate `DefaultersExport` class handles XLSX download. Two stub jobs (`GenerateAttendanceReport`, `SendAbsenceNotifications`) are created now; full implementations are Phase 6.

**Tech Stack:** Filament v4, Livewire v3, maatwebsite/excel ^3.1, `array` cache driver (tests), SQLite (tests)

---

## File Map

| Action | Path |
|---|---|
| Create | `app/Jobs/GenerateAttendanceReport.php` |
| Create | `app/Jobs/SendAbsenceNotifications.php` |
| Create | `app/Exports/DefaultersExport.php` |
| Create | `app/Filament/Admin/Pages/ReportPage.php` |
| Create | `resources/views/filament/admin/pages/report-page.blade.php` |
| Create | `app/Filament/Admin/Pages/DefaulterListPage.php` |
| Create | `resources/views/filament/admin/pages/defaulter-list-page.blade.php` |
| Create | `tests/Feature/Admin/ReportPageTest.php` |
| Create | `tests/Feature/Admin/DefaulterListPageTest.php` |

---

## Task 1: Create Stub Jobs

**Files:**
- Create: `app/Jobs/GenerateAttendanceReport.php`
- Create: `app/Jobs/SendAbsenceNotifications.php`

- [ ] **Step 1: Create GenerateAttendanceReport**

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAttendanceReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        // Phase 6.1 implementation
    }

    public function failed(\Throwable $exception): void
    {
        // Phase 6.1 implementation
    }
}
```

- [ ] **Step 2: Create SendAbsenceNotifications**

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        // Phase 6.3 implementation
    }

    public function failed(\Throwable $exception): void
    {
        // Phase 6.3 implementation
    }
}
```

- [ ] **Step 3: Run Pint and commit**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Jobs/GenerateAttendanceReport.php app/Jobs/SendAbsenceNotifications.php
git commit -m "feat: add stub jobs for report generation and absence notifications (Phase 4.10)"
```

---

## Task 2: Write ReportPage Tests (TDD — failing first)

**Files:**
- Test: `tests/Feature/Admin/ReportPageTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Enums\ExportFormat;
use App\Filament\Admin\Pages\ReportPage;
use App\Jobs\GenerateAttendanceReport;
use App\Models\AdminRoleAssignment;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForReport(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_view_report_page', function () {
    $dept = Department::factory()->create();
    $admin = adminForReport($dept);

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertSuccessful();
});

test('report_form_dispatches_generate_report_job', function () {
    Queue::fake();

    $dept = Department::factory()->create();
    $admin = adminForReport($dept);

    $this->actingAs($admin);

    livewire(ReportPage::class)
        ->fillForm([
            'type' => 'department',
            'department_id' => $dept->id,
            'from' => '2026-01-01',
            'to' => '2026-05-01',
            'format' => ExportFormat::Xlsx->value,
        ])
        ->call('generateReport')
        ->assertNotified();

    Queue::assertPushed(GenerateAttendanceReport::class, function ($job) use ($dept) {
        return $job->type === 'department'
            && $job->departmentId === $dept->id
            && $job->format === ExportFormat::Xlsx->value;
    });
});

test('report_form_requires_date_range', function () {
    $dept = Department::factory()->create();
    $admin = adminForReport($dept);

    $this->actingAs($admin);

    livewire(ReportPage::class)
        ->fillForm([
            'type' => 'department',
            'from' => null,
            'to' => null,
            'format' => ExportFormat::Pdf->value,
        ])
        ->call('generateReport')
        ->assertHasFormErrors(['from' => 'required', 'to' => 'required']);
});
```

- [ ] **Step 2: Run tests and confirm they fail**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=ReportPage
```

Expected: 3 failures with "class not found" or "route not found" errors.

---

## Task 3: Implement ReportPage

**Files:**
- Create: `app/Filament/Admin/Pages/ReportPage.php`
- Create: `resources/views/filament/admin/pages/report-page.blade.php`

- [ ] **Step 1: Create the blade view**

```blade
<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>
</x-filament-panels::page>
```

- [ ] **Step 2: Create ReportPage**

```php
<?php

namespace App\Filament\Admin\Pages;

use App\Enums\ExportFormat;
use App\Jobs\GenerateAttendanceReport;
use App\Models\Course;
use App\Models\Department;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ReportPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Generate Report';

    protected static ?string $slug = 'reports';

    protected string $view = 'filament.admin.pages.report-page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Report Type')
                    ->options([
                        'department' => 'Department',
                        'course' => 'Course',
                        'faculty' => 'Faculty',
                        'student' => 'Student',
                        'date_range' => 'Date Range',
                    ])
                    ->required(),
                Select::make('department_id')
                    ->label('Department')
                    ->options(fn () => Department::pluck('name', 'id'))
                    ->searchable(),
                Select::make('course_id')
                    ->label('Course')
                    ->options(fn () => Course::pluck('code', 'id'))
                    ->searchable(),
                DatePicker::make('from')
                    ->label('From')
                    ->required(),
                DatePicker::make('to')
                    ->label('To')
                    ->required(),
                Select::make('format')
                    ->label('Format')
                    ->options(ExportFormat::class)
                    ->default(ExportFormat::Pdf->value)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $data = $this->form->getState();

        GenerateAttendanceReport::dispatch(
            type: $data['type'],
            departmentId: $data['department_id'] ?? null,
            courseId: $data['course_id'] ?? null,
            from: $data['from'],
            to: $data['to'],
            format: $data['format'],
            requestedBy: auth()->id(),
        );

        Notification::make()
            ->title('Report queued')
            ->body('Your report is being generated and will be available for download shortly.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_report')
                ->label('Generate Report')
                ->action('generateReport'),
        ];
    }
}
```

- [ ] **Step 3: Run the ReportPage tests — should pass**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=ReportPage
```

Expected: 3 passing.

- [ ] **Step 4: Run Pint and commit**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/Admin/Pages/ReportPage.php resources/views/filament/admin/pages/report-page.blade.php tests/Feature/Admin/ReportPageTest.php
git commit -m "feat: add Admin ReportPage with GenerateAttendanceReport dispatch (Phase 4.10)"
```

---

## Task 4: Write DefaulterListPage Tests (TDD — failing first)

**Files:**
- Test: `tests/Feature/Admin/DefaulterListPageTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\SessionStatus;
use App\Filament\Admin\Pages\DefaulterListPage;
use App\Jobs\SendAbsenceNotifications;
use App\Models\AdminRoleAssignment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForDefaulters(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

/**
 * Creates `$sessionCount` closed sessions for the given course, then creates
 * `$attendedCount` present records for the given student+enrollment.
 * Returns the created enrollment.
 */
function createStudentWithAttendance(
    Student $student,
    Course $course,
    int $sessionCount,
    int $attendedCount
): Enrollment {
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => EnrollmentStatus::Active->value,
    ]);

    $faculty = Faculty::factory()->create();
    $sessions = AttendanceSession::factory()->count($sessionCount)->create([
        'course_id' => $course->id,
        'faculty_id' => $faculty->id,
        'status' => SessionStatus::Closed->value,
    ]);

    foreach ($sessions->take($attendedCount) as $session) {
        AttendanceRecord::factory()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'status' => AttendanceStatus::Present->value,
        ]);
    }

    return $enrollment;
}

test('admin_can_view_defaulter_list', function () {
    $dept = Department::factory()->create();
    $admin = adminForDefaulters($dept);

    $this->actingAs($admin)
        ->get('/admin/defaulters')
        ->assertSuccessful();
});

test('defaulter_list_only_shows_students_below_minimum_attendance', function () {
    $dept = Department::factory()->create();
    $admin = adminForDefaulters($dept);

    // min_attendance_pct = 75%; strictly below means < 75
    $course = Course::factory()->create([
        'department_id' => $dept->id,
        'min_attendance_pct' => 75.00,
    ]);

    // Student A: 3/4 = 75% — exactly at the minimum, NOT a defaulter
    $studentA = Student::factory()->create(['department_id' => $dept->id]);
    $enrollmentA = createStudentWithAttendance($studentA, $course, sessionCount: 4, attendedCount: 3);

    // Student B: 2/4 = 50% < 75% — IS a defaulter
    $studentB = Student::factory()->create(['department_id' => $dept->id]);
    $enrollmentB = createStudentWithAttendance($studentB, $course, sessionCount: 4, attendedCount: 2);

    $this->actingAs($admin);

    livewire(DefaulterListPage::class)
        ->assertCanSeeTableRecords([$enrollmentB])
        ->assertCanNotSeeTableRecords([$enrollmentA]);
});

test('notify_action_dispatches_absence_notifications_job', function () {
    Queue::fake();

    $dept = Department::factory()->create();
    $admin = adminForDefaulters($dept);

    $course = Course::factory()->create([
        'department_id' => $dept->id,
        'min_attendance_pct' => 75.00,
    ]);

    // 1/4 sessions attended = 25%, well below 75%
    $student = Student::factory()->create(['department_id' => $dept->id]);
    createStudentWithAttendance($student, $course, sessionCount: 4, attendedCount: 1);

    $this->actingAs($admin);

    livewire(DefaulterListPage::class)
        ->callAction('notify')
        ->assertNotified();

    Queue::assertPushed(SendAbsenceNotifications::class, function (SendAbsenceNotifications $job) use ($student, $course) {
        return in_array($student->id, $job->studentIds, true)
            && $job->courseId === $course->id;
    });
});
```

- [ ] **Step 2: Run tests and confirm they fail**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=DefaulterList
```

Expected: 3 failures — class/route not found.

---

## Task 5: Create DefaultersExport

**Files:**
- Create: `app/Exports/DefaultersExport.php`

This export class is used by the ExportAction in DefaulterListPage. It receives enrollment IDs and builds the XLSX rows.

- [ ] **Step 1: Create the export class**

```php
<?php

namespace App\Exports;

use App\Enums\AttendanceStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DefaultersExport implements FromCollection, WithHeadings
{
    /**
     * @param  array<int, int>  $enrollmentIds
     */
    public function __construct(private readonly array $enrollmentIds) {}

    public function collection(): \Illuminate\Support\Collection
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
```

- [ ] **Step 2: Run Pint**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
```

---

## Task 6: Implement DefaulterListPage

**Files:**
- Create: `app/Filament/Admin/Pages/DefaulterListPage.php`
- Create: `resources/views/filament/admin/pages/defaulter-list-page.blade.php`

- [ ] **Step 1: Create the blade view**

```blade
<x-filament-panels::page>
    {{ $this->table }}
</x-filament-panels::page>
```

- [ ] **Step 2: Create DefaulterListPage**

```php
<?php

namespace App\Filament\Admin\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\SessionStatus;
use App\Exports\DefaultersExport;
use App\Jobs\SendAbsenceNotifications;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class DefaulterListPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Defaulter List';

    protected static ?string $slug = 'defaulters';

    protected string $view = 'filament.admin.pages.defaulter-list-page';

    public function table(Table $table): Table
    {
        $ids = $this->getDefaulterEnrollmentIds();

        return $table
            ->query(
                Enrollment::query()
                    ->with(['student.user', 'course'])
                    ->addSelect([
                        'enrollments.*',
                        \Illuminate\Support\Facades\DB::raw('(
                            SELECT COUNT(*)
                            FROM attendance_sessions
                            WHERE attendance_sessions.course_id = enrollments.course_id
                            AND attendance_sessions.status = "closed"
                        ) AS total_sessions'),
                        \Illuminate\Support\Facades\DB::raw('(
                            SELECT COUNT(*)
                            FROM attendance_records
                            INNER JOIN attendance_sessions ON attendance_records.attendance_session_id = attendance_sessions.id
                            WHERE attendance_records.student_id = enrollments.student_id
                            AND attendance_sessions.course_id = enrollments.course_id
                            AND attendance_records.status IN ("present", "late")
                        ) AS attended_sessions'),
                    ])
                    ->whereIn('id', $ids)
            )
            ->columns([
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('course.code')
                    ->label('Course'),
                TextColumn::make('attended_total')
                    ->label('Attended / Total')
                    ->state(fn (Enrollment $record): string => "{$record->attended_sessions} / {$record->total_sessions}"),
                TextColumn::make('attendance_pct')
                    ->label('Attendance %')
                    ->state(fn (Enrollment $record): string => $record->total_sessions > 0
                        ? number_format($record->attended_sessions / $record->total_sessions * 100, 1).'%'
                        : '0%'
                    ),
                TextColumn::make('course.min_attendance_pct')
                    ->label('Minimum %')
                    ->suffix('%'),
            ])
            ->emptyStateHeading('No defaulters found')
            ->emptyStateDescription('All enrolled students meet the minimum attendance requirement.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export XLSX')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function (): mixed {
                    $ids = $this->getDefaulterEnrollmentIds();

                    return Excel::download(new DefaultersExport($ids), 'defaulters.xlsx');
                }),

            Action::make('notify')
                ->label('Notify Students')
                ->icon(Heroicon::OutlinedBell)
                ->requiresConfirmation()
                ->modalHeading('Notify Defaulting Students')
                ->modalDescription('This will send absence notification emails to all defaulting students. Continue?')
                ->action(function (): void {
                    $defaulterData = $this->getDefaulterData();

                    collect($defaulterData)
                        ->groupBy('course_id')
                        ->each(function (\Illuminate\Support\Collection $group, int|string $courseId): void {
                            SendAbsenceNotifications::dispatch(
                                studentIds: $group->pluck('student_id')->map(fn ($id) => (int) $id)->toArray(),
                                courseId: (int) $courseId,
                            );
                        });

                    Notification::make()
                        ->title('Notifications queued')
                        ->body('Absence notification emails have been queued for all defaulting students.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * Returns cached array of enrollment IDs where the student is below min attendance.
     *
     * @return array<int, int>
     */
    protected function getDefaulterEnrollmentIds(): array
    {
        return array_column($this->getDefaulterData(), 'id');
    }

    /**
     * Returns cached array of ['id', 'student_id', 'course_id'] for defaulting enrollments.
     *
     * @return array<int, array{id: int, student_id: int, course_id: int}>
     */
    protected function getDefaulterData(): array
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        return Cache::remember("defaulters.dept.{$departmentId}", 300, function () use ($departmentId) {
            return Enrollment::query()
                ->where('status', EnrollmentStatus::Active->value)
                ->whereHas('student', fn (Builder $q) => $q->where('department_id', $departmentId))
                ->with(['course'])
                ->get()
                ->filter(function (Enrollment $enrollment): bool {
                    $total = AttendanceSession::where('course_id', $enrollment->course_id)
                        ->where('status', SessionStatus::Closed->value)
                        ->count();

                    if ($total === 0) {
                        return false;
                    }

                    $attended = AttendanceRecord::where('student_id', $enrollment->student_id)
                        ->whereHas('session', fn (Builder $q) => $q->where('course_id', $enrollment->course_id))
                        ->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])
                        ->count();

                    return ($attended / $total * 100) < (float) $enrollment->course->min_attendance_pct;
                })
                ->map(fn (Enrollment $enrollment): array => [
                    'id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'course_id' => $enrollment->course_id,
                ])
                ->values()
                ->toArray();
        });
    }
}
```

- [ ] **Step 3: Run the DefaulterListPage tests — should pass**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=DefaulterList
```

Expected: 3 passing.

- [ ] **Step 4: Run all Admin tests to catch regressions**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/Admin/
```

Expected: all green.

- [ ] **Step 5: Run Pint and commit**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/Admin/Pages/DefaulterListPage.php \
        app/Exports/DefaultersExport.php \
        resources/views/filament/admin/pages/defaulter-list-page.blade.php \
        tests/Feature/Admin/DefaulterListPageTest.php
git commit -m "feat: add Admin DefaulterListPage with cached defaulter query and notify action (Phase 4.10)"
```

---

## Self-Review Against Spec

| Spec requirement | Covered by |
|---|---|
| ReportPage: Select(type) | Task 3 — `Select::make('type')` |
| ReportPage: Select(department_id) | Task 3 — `Select::make('department_id')` |
| ReportPage: Select(course_id) | Task 3 — `Select::make('course_id')` |
| ReportPage: DatePicker(from) required | Task 3 — `->required()` on `from` |
| ReportPage: DatePicker(to) required | Task 3 — `->required()` on `to` |
| ReportPage: Select(format: pdf\|csv\|xlsx) | Task 3 — `Select::make('format')->options(ExportFormat::class)` |
| ReportPage: dispatches GenerateAttendanceReport | Task 3 — `generateReport()` method |
| DefaulterListPage: student name col | Task 6 — `TextColumn::make('student.user.name')` |
| DefaulterListPage: course col | Task 6 — `TextColumn::make('course.code')` |
| DefaulterListPage: attended/total col | Task 6 — state closure |
| DefaulterListPage: attendance % col | Task 6 — state closure |
| DefaulterListPage: minimum % col | Task 6 — `TextColumn::make('course.min_attendance_pct')` |
| DefaulterListPage: ExportAction → XLSX | Task 6 — `Excel::download(new DefaultersExport(...))` |
| DefaulterListPage: NotifyAction → dispatches SendAbsenceNotifications | Task 6 — `SendAbsenceNotifications::dispatch(...)` |
| DefaulterListPage: cached 5 minutes | Task 6 — `Cache::remember(..., 300, ...)` |
| test_admin_can_view_report_page | Task 2 |
| test_report_form_dispatches_generate_report_job | Task 2 |
| test_report_form_requires_date_range | Task 2 |
| test_admin_can_view_defaulter_list | Task 4 |
| test_defaulter_list_only_shows_students_below_minimum_attendance | Task 4 |
| test_notify_action_dispatches_absence_notifications_job | Task 4 |
