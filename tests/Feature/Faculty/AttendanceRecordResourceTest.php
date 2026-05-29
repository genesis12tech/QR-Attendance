<?php

use App\Filament\Faculty\Resources\AttendanceRecords\Pages\ListAttendanceRecords;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('faculty'));
});

function attendanceRecordForFaculty(Faculty $faculty): AttendanceRecord
{
    $session = AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);

    return AttendanceRecord::factory()->create(['session_id' => $session->id]);
}

test('faculty_can_list_records_for_own_sessions', function () {
    $faculty = Faculty::factory()->create();
    $record = attendanceRecordForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListAttendanceRecords::class)
        ->assertCanSeeTableRecords([$record]);
});

test('faculty_cannot_see_records_from_other_sessions', function () {
    $faculty = Faculty::factory()->create();
    $otherFaculty = Faculty::factory()->create();

    $myRecord = attendanceRecordForFaculty($faculty);
    $otherRecord = attendanceRecordForFaculty($otherFaculty);

    $this->actingAs($faculty->user);

    livewire(ListAttendanceRecords::class)
        ->assertCanSeeTableRecords([$myRecord])
        ->assertCanNotSeeTableRecords([$otherRecord]);
});

test('faculty_has_no_override_action', function () {
    $faculty = Faculty::factory()->create();
    $record = attendanceRecordForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListAttendanceRecords::class)
        ->assertTableActionDoesNotExist('override', null, $record);
});
