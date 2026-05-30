<?php

use App\Enums\DayOfWeek;
use App\Enums\SessionStatus;
use App\Filament\Faculty\Pages\QrDisplayPage;
use App\Filament\Faculty\Pages\TimetablePage;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\Timetable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('faculty'));
});

function facultyUserForTimetable(): array
{
    $faculty = Faculty::factory()->create();

    return [$faculty->user, $faculty];
}

test('faculty_can_view_own_timetable', function () {
    [$user] = facultyUserForTimetable();
    $this->actingAs($user);

    livewire(TimetablePage::class)
        ->assertSuccessful();
});

test('timetable_shows_correct_week_slots', function () {
    [$user, $faculty] = facultyUserForTimetable();

    $todayDayOfWeek = DayOfWeek::from(strtolower(now()->format('l')));
    $timetable = Timetable::factory()->create([
        'faculty_id' => $faculty->id,
        'day_of_week' => $todayDayOfWeek,
        'effective_from' => now()->subDay(),
        'effective_until' => null,
    ]);

    $this->actingAs($user);

    livewire(TimetablePage::class)
        ->assertSet(
            'timetableSlots',
            fn ($slots) => collect($slots)->pluck('id')->contains($timetable->id),
        );
});

test('start_session_from_timetable_creates_session', function () {
    [$user, $faculty] = facultyUserForTimetable();

    $todayDayOfWeek = DayOfWeek::from(strtolower(now()->format('l')));
    $timetable = Timetable::factory()->create([
        'faculty_id' => $faculty->id,
        'day_of_week' => $todayDayOfWeek,
        'effective_from' => now()->subDay(),
    ]);

    $this->actingAs($user);

    livewire(TimetablePage::class)
        ->call('startSession', $timetable->id)
        ->assertNotified()
        ->assertRedirect(QrDisplayPage::getUrl());

    assertDatabaseHas(AttendanceSession::class, [
        'faculty_id' => $faculty->id,
        'timetable_id' => $timetable->id,
        'status' => SessionStatus::Active->value,
    ]);
});

test('start_session_fails_if_active_session_already_exists_for_slot', function () {
    [$user, $faculty] = facultyUserForTimetable();

    $todayDayOfWeek = DayOfWeek::from(strtolower(now()->format('l')));
    $timetable = Timetable::factory()->create([
        'faculty_id' => $faculty->id,
        'day_of_week' => $todayDayOfWeek,
        'effective_from' => now()->subDay(),
    ]);

    AttendanceSession::factory()->active()->create([
        'faculty_id' => $faculty->id,
        'timetable_id' => $timetable->id,
        'started_at' => now(),
    ]);

    $this->actingAs($user);

    livewire(TimetablePage::class)
        ->call('startSession', $timetable->id)
        ->assertNotified();

    expect(
        AttendanceSession::where([
            'faculty_id' => $faculty->id,
            'timetable_id' => $timetable->id,
            'status' => SessionStatus::Active->value,
        ])->count()
    )->toBe(1);
});
