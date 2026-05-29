<?php

use App\Filament\Faculty\Resources\ProxyFlags\Pages\ListProxyFlags;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\ProxyFlag;
use App\Models\SystemSetting;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('faculty'));
});

function proxyFlagForFaculty(Faculty $faculty): ProxyFlag
{
    $session = AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);
    $record = AttendanceRecord::factory()->pendingReview()->create(['session_id' => $session->id]);

    return ProxyFlag::factory()->pending()->create(['attendance_record_id' => $record->id]);
}

test('faculty_can_list_flags_for_own_sessions', function () {
    $faculty = Faculty::factory()->create();
    $flag = proxyFlagForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListProxyFlags::class)
        ->assertCanSeeTableRecords([$flag]);
});

test('faculty_cannot_see_flags_from_other_sessions', function () {
    $faculty = Faculty::factory()->create();
    $otherFaculty = Faculty::factory()->create();

    $myFlag = proxyFlagForFaculty($faculty);
    $otherFlag = proxyFlagForFaculty($otherFaculty);

    $this->actingAs($faculty->user);

    livewire(ListProxyFlags::class)
        ->assertCanSeeTableRecords([$myFlag])
        ->assertCanNotSeeTableRecords([$otherFlag]);
});

test('allow_deny_actions_visible_when_policy_permits', function () {
    SystemSetting::set('faculty_can_review_flags', 'true');

    $faculty = Faculty::factory()->create();
    $flag = proxyFlagForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListProxyFlags::class)
        ->assertTableActionVisible('allow', $flag)
        ->assertTableActionVisible('deny', $flag);
});

test('allow_deny_actions_hidden_when_policy_disallows', function () {
    SystemSetting::set('faculty_can_review_flags', 'false');

    $faculty = Faculty::factory()->create();
    $flag = proxyFlagForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListProxyFlags::class)
        ->assertTableActionHidden('allow', $flag)
        ->assertTableActionHidden('deny', $flag);
});
