<?php

namespace App\Filament\Faculty\Widgets;

use App\Enums\DayOfWeek;
use App\Enums\SessionStatus;
use App\Jobs\FinalizeAttendanceSession;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Timetable;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class LiveSessionBannerWidget extends Widget
{
    protected string $view = 'filament.faculty.widgets.live-session-banner-widget';

    protected ?string $pollingInterval = '5s';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $facultyId = auth()->user()?->faculty?->id;

        $activeSession = AttendanceSession::with(['course', 'room'])
            ->where('faculty_id', $facultyId)
            ->where('status', SessionStatus::Active)
            ->latest()
            ->first();

        $todaySlots = [];

        if (! $activeSession) {
            $todayDow = DayOfWeek::from(strtolower(now()->format('l')));

            $todaySlots = Timetable::with(['course', 'classGroup', 'room'])
                ->where('faculty_id', $facultyId)
                ->where('day_of_week', $todayDow)
                ->where('effective_from', '<=', today())
                ->where(function ($query) {
                    $query->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', today());
                })
                ->orderBy('start_time')
                ->get()
                ->toArray();
        }

        return [
            'activeSession' => $activeSession,
            'todaySlots' => $todaySlots,
        ];
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

        Notification::make()->title('Session started')->success()->send();
    }

    public function closeSession(int $sessionId): void
    {
        $session = AttendanceSession::find($sessionId);

        if (! $session) {
            return;
        }

        $session->update([
            'status' => SessionStatus::Closed,
            'closed_at' => now(),
        ]);

        FinalizeAttendanceSession::dispatch($session);

        Notification::make()->title('Session closed')->success()->send();
    }
}
