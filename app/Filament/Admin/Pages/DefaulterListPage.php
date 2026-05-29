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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        // $ids is captured at component mount from the 5-minute cache; the table
        // reflects that snapshot for the page lifetime. A page refresh picks up
        // any cache expiry.
        $ids = $this->getDefaulterEnrollmentIds();

        return $table
            ->query(
                Enrollment::query()
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
                            INNER JOIN attendance_sessions ON attendance_records.session_id = attendance_sessions.id
                            WHERE attendance_records.student_id = enrollments.student_id
                            AND attendance_sessions.course_id = enrollments.course_id
                            AND attendance_sessions.status = "closed"
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

                    if (empty($defaulterData)) {
                        Notification::make()
                            ->title('No defaulters')
                            ->body('There are no defaulting students to notify.')
                            ->warning()
                            ->send();

                        return;
                    }

                    collect($defaulterData)
                        ->groupBy('course_id')
                        ->each(function (Collection $group, int|string $courseId): void {
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
     * Cached for 5 minutes per department.
     *
     * @return array<int, array{id: int, student_id: int, course_id: int}>
     */
    protected function getDefaulterData(): array
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        if ($departmentId === null) {
            return [];
        }

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
                        ->whereHas('session', fn (Builder $q) => $q
                            ->where('course_id', $enrollment->course_id)
                            ->where('status', SessionStatus::Closed->value)
                        )
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
