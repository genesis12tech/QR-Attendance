<?php

use App\Enums\SessionStatus;
use App\Filament\Faculty\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Jobs\FinalizeAttendanceSession;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('faculty'));
});

function facultyUserForSession(): array
{
    $faculty = Faculty::factory()->create();

    return [$faculty->user, $faculty];
}

test('faculty_can_list_own_sessions', function () {
    [$user, $faculty] = facultyUserForSession();
    $sessions = AttendanceSession::factory()->count(2)->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(ListAttendanceSessions::class)
        ->assertCanSeeTableRecords($sessions);
});

test('faculty_cannot_see_other_faculty_sessions', function () {
    [$user, $faculty] = facultyUserForSession();
    $otherFaculty = Faculty::factory()->create();

    $ownSession = AttendanceSession::factory()->create(['faculty_id' => $faculty->id]);
    $otherSession = AttendanceSession::factory()->create(['faculty_id' => $otherFaculty->id]);

    $this->actingAs($user);

    livewire(ListAttendanceSessions::class)
        ->assertCanSeeTableRecords([$ownSession])
        ->assertCanNotSeeTableRecords([$otherSession]);
});

test('start_action_sets_status_to_active', function () {
    [$user, $faculty] = facultyUserForSession();
    $session = AttendanceSession::factory()->pending()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(ListAttendanceSessions::class)
        ->callAction(TestAction::make('start')->table($session))
        ->assertNotified();

    assertDatabaseHas(AttendanceSession::class, [
        'id' => $session->id,
        'status' => SessionStatus::Active->value,
    ]);
});

test('start_action_redirects_to_qr_display_page', function () {
    [$user, $faculty] = facultyUserForSession();
    $session = AttendanceSession::factory()->pending()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(ListAttendanceSessions::class)
        ->callAction(TestAction::make('start')->table($session))
        ->assertRedirect();
});

test('close_action_dispatches_finalize_job', function () {
    Bus::fake();

    [$user, $faculty] = facultyUserForSession();
    $session = AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(ListAttendanceSessions::class)
        ->callAction(TestAction::make('close')->table($session))
        ->assertNotified();

    Bus::assertDispatched(FinalizeAttendanceSession::class);
});

test('reopen_action_is_only_available_within_grace_window', function () {
    [$user, $faculty] = facultyUserForSession();

    $recentlyClosed = AttendanceSession::factory()->closed()->create([
        'faculty_id' => $faculty->id,
        'closed_at' => now()->subMinutes(5),
    ]);

    $oldClosed = AttendanceSession::factory()->closed()->create([
        'faculty_id' => $faculty->id,
        'closed_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($user);

    livewire(ListAttendanceSessions::class)
        ->assertTableActionVisible('reopen', $recentlyClosed)
        ->assertTableActionHidden('reopen', $oldClosed);
});
