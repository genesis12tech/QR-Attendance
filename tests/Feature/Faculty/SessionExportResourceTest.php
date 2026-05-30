<?php

use App\Filament\Faculty\Resources\SessionExports\Pages\ListSessionExports;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\SessionExport;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('faculty'));
});

function sessionExportForFaculty(Faculty $faculty, string $state = 'pending'): SessionExport
{
    $session = AttendanceSession::factory()->closed()->create(['faculty_id' => $faculty->id]);

    return SessionExport::factory()->{$state}()->create([
        'session_id' => $session->id,
        'requested_by' => $faculty->user_id,
    ]);
}

test('faculty_can_list_session_exports', function () {
    $faculty = Faculty::factory()->create();
    $export = sessionExportForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListSessionExports::class)
        ->assertCanSeeTableRecords([$export]);
});

test('download_action_provides_signed_url_when_ready', function () {
    $faculty = Faculty::factory()->create();
    $export = sessionExportForFaculty($faculty, 'ready');

    $this->actingAs($faculty->user);

    livewire(ListSessionExports::class)
        ->callAction(TestAction::make('download')->table($export))
        ->assertRedirect();
});

test('download_action_shows_processing_message_when_pending', function () {
    $faculty = Faculty::factory()->create();
    $export = sessionExportForFaculty($faculty, 'pending');

    $this->actingAs($faculty->user);

    livewire(ListSessionExports::class)
        ->callAction(TestAction::make('download')->table($export))
        ->assertNotified('Processing…');
});

test('faculty_can_delete_export', function () {
    $faculty = Faculty::factory()->create();
    $export = sessionExportForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListSessionExports::class)
        ->callAction(TestAction::make('delete')->table($export));

    assertDatabaseMissing(SessionExport::class, ['id' => $export->id]);
});
