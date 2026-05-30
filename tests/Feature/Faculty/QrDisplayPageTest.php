<?php

use App\Enums\SessionStatus;
use App\Filament\Faculty\Pages\QrDisplayPage;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Services\QRChallengeService;
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

function facultyUserForQr(): array
{
    $faculty = Faculty::factory()->create();

    return [$faculty->user, $faculty];
}

test('faculty_can_access_qr_display_page_for_active_session', function () {
    $this->mock(QRChallengeService::class)
        ->shouldReceive('generateForSession')
        ->andReturn('fake-qr-string');

    [$user, $faculty] = facultyUserForQr();
    AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(QrDisplayPage::class)
        ->assertSuccessful();
});

test('qr_display_page_redirects_when_no_active_session', function () {
    [$user, $faculty] = facultyUserForQr();

    $this->actingAs($user);

    livewire(QrDisplayPage::class)
        ->assertRedirect();
});

test('qr_string_is_populated_on_mount', function () {
    $this->mock(QRChallengeService::class)
        ->shouldReceive('generateForSession')
        ->andReturn('fake-qr-string');

    [$user, $faculty] = facultyUserForQr();
    AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(QrDisplayPage::class)
        ->assertSet('qrString', 'fake-qr-string');
});

test('refresh_qr_generates_new_qr_string', function () {
    $this->mock(QRChallengeService::class)
        ->shouldReceive('generateForSession')
        ->andReturn('qr-string-1', 'qr-string-2');

    [$user, $faculty] = facultyUserForQr();
    AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(QrDisplayPage::class)
        ->assertSet('qrString', 'qr-string-1')
        ->call('refreshQr')
        ->assertSet('qrString', 'qr-string-2');
});

test('close_session_action_sets_status_to_closed', function () {
    Bus::fake();

    $this->mock(QRChallengeService::class)
        ->shouldReceive('generateForSession')
        ->andReturn('fake-qr-string');

    [$user, $faculty] = facultyUserForQr();
    $session = AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(QrDisplayPage::class)
        ->callAction('close_session')
        ->assertNotified();

    assertDatabaseHas(AttendanceSession::class, [
        'id' => $session->id,
        'status' => SessionStatus::Closed->value,
    ]);
});

test('pause_session_action_sets_status_to_paused', function () {
    $this->mock(QRChallengeService::class)
        ->shouldReceive('generateForSession')
        ->andReturn('fake-qr-string');

    [$user, $faculty] = facultyUserForQr();
    $session = AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(QrDisplayPage::class)
        ->callAction('pause_session')
        ->assertNotified();

    assertDatabaseHas(AttendanceSession::class, [
        'id' => $session->id,
        'status' => SessionStatus::Paused->value,
    ]);
});

test('force_refresh_generates_new_qr_immediately', function () {
    $this->mock(QRChallengeService::class)
        ->shouldReceive('generateForSession')
        ->andReturn('initial-qr', 'refreshed-qr');

    [$user, $faculty] = facultyUserForQr();
    AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    $this->actingAs($user);

    livewire(QrDisplayPage::class)
        ->assertSet('qrString', 'initial-qr')
        ->callAction('force_refresh_qr')
        ->assertSet('qrString', 'refreshed-qr');
});
