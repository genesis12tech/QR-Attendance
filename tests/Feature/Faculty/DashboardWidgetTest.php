<?php

use App\Enums\DayOfWeek;
use App\Filament\Faculty\Widgets\FlaggedScanAlertWidget;
use App\Filament\Faculty\Widgets\LiveSessionBannerWidget;
use App\Filament\Faculty\Widgets\RecentScanFeedWidget;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\Timetable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('faculty'));
});

function facultyUserForWidgets(): array
{
    $faculty = Faculty::factory()->create();

    return [$faculty->user, $faculty];
}

test('live_session_banner_shows_active_session_for_faculty', function () {
    [$user, $faculty] = facultyUserForWidgets();
    $session = AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(LiveSessionBannerWidget::class)
        ->assertSee($session->course->code);
});

test('live_session_banner_shows_timetable_when_no_active_session', function () {
    [$user, $faculty] = facultyUserForWidgets();

    $todayDow = DayOfWeek::from(strtolower(now()->format('l')));
    $timetable = Timetable::factory()->create([
        'faculty_id' => $faculty->id,
        'day_of_week' => $todayDow,
        'effective_from' => today()->subDays(7)->toDateString(),
    ]);

    $this->actingAs($user);

    livewire(LiveSessionBannerWidget::class)
        ->assertSee($timetable->course->code);
});

test('recent_scan_feed_shows_last_10_records', function () {
    [$user, $faculty] = facultyUserForWidgets();
    $session = AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);
    AttendanceRecord::factory()->count(12)->create(['session_id' => $session->id]);

    $this->actingAs($user);

    livewire(RecentScanFeedWidget::class)
        ->assertSuccessful()
        ->assertCountTableRecords(10);
});

test('flagged_scan_alert_is_hidden_when_no_active_session', function () {
    [$user] = facultyUserForWidgets();

    $this->actingAs($user);

    livewire(FlaggedScanAlertWidget::class)
        ->assertSuccessful()
        ->assertDontSeeHtml('wire:click="allow(');
});
