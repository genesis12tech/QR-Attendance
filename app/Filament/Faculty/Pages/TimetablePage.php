<?php

namespace App\Filament\Faculty\Pages;

use App\Enums\DayOfWeek;
use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Timetable;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class TimetablePage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'My Classes';

    protected static ?string $navigationLabel = 'My Timetable';

    protected static ?string $slug = 'timetable';

    protected string $view = 'filament.faculty.pages.timetable-page';

    /** @var array<int, array<string, mixed>> */
    public array $timetableSlots = [];

    public function mount(): void
    {
        $this->loadTimetableSlots();
    }

    /** @return array<int, array<string, mixed>> */
    private function loadTimetableSlots(): void
    {
        $facultyId = auth()->user()?->faculty?->id;

        $dayOrder = array_column(DayOfWeek::cases(), 'value');

        $this->timetableSlots = Timetable::query()
            ->with(['course', 'classGroup', 'room'])
            ->where('faculty_id', $facultyId)
            ->where('effective_from', '<=', today())
            ->where(function ($query) {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', today());
            })
            ->get()
            ->map(fn (Timetable $timetable) => [
                'id' => $timetable->id,
                'day' => ucfirst($timetable->day_of_week->value),
                'day_order' => (int) array_search($timetable->day_of_week->value, $dayOrder),
                'course_code' => $timetable->course?->code,
                'class_group_name' => $timetable->classGroup?->name,
                'room_name' => $timetable->room?->name,
                'start_time' => $timetable->start_time,
                'end_time' => $timetable->end_time,
            ])
            ->sortBy(['day_order', 'start_time'])
            ->values()
            ->toArray();
    }

    public function startSession(int $timetableId): void
    {
        $timetable = Timetable::find($timetableId);

        if (! $timetable) {
            return;
        }

        $alreadyActive = AttendanceSession::where('timetable_id', $timetableId)
            ->where('status', SessionStatus::Active)
            ->whereDate('started_at', today())
            ->exists();

        if ($alreadyActive) {
            Notification::make()
                ->title('An active session already exists for this slot today')
                ->warning()
                ->send();

            return;
        }

        $session = AttendanceSession::create([
            'faculty_id' => $timetable->faculty_id,
            'course_id' => $timetable->course_id,
            'class_group_id' => $timetable->class_group_id,
            'room_id' => $timetable->room_id,
            'timetable_id' => $timetable->id,
            'status' => SessionStatus::Active,
            'started_at' => now(),
        ]);

        AuditLog::record('session.started', $session);

        Notification::make()
            ->title('Session started')
            ->success()
            ->send();

        $this->redirect(QrDisplayPage::getUrl());
    }
}
