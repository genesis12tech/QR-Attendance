<?php

use App\Enums\DayOfWeek;
use App\Filament\Admin\Resources\Timetables\Pages\CreateTimetable;
use App\Filament\Admin\Resources\Timetables\Pages\ListTimetables;
use App\Models\AdminRoleAssignment;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Timetable;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForTimetable(): User
{
    $dept = Department::factory()->create();
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $dept->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_list_timetables', function () {
    $admin = adminForTimetable();
    $timetables = Timetable::factory()->count(3)->create();

    $this->actingAs($admin);

    livewire(ListTimetables::class)
        ->assertCanSeeTableRecords($timetables);
});

test('admin_can_create_timetable_entry', function () {
    $admin = adminForTimetable();
    $course = Course::factory()->create();
    $classGroup = ClassGroup::factory()->create(['course_id' => $course->id]);
    $faculty = Faculty::factory()->create();
    $room = Room::factory()->create();

    $this->actingAs($admin);

    livewire(CreateTimetable::class)
        ->fillForm([
            'course_id' => $course->id,
            'class_group_id' => $classGroup->id,
            'faculty_id' => $faculty->id,
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::Monday->value,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'effective_from' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Timetable::class, [
        'course_id' => $course->id,
        'day_of_week' => DayOfWeek::Monday->value,
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
});

test('start_time_must_be_before_end_time', function () {
    $admin = adminForTimetable();
    $course = Course::factory()->create();
    $classGroup = ClassGroup::factory()->create(['course_id' => $course->id]);
    $faculty = Faculty::factory()->create();
    $room = Room::factory()->create();

    $this->actingAs($admin);

    livewire(CreateTimetable::class)
        ->fillForm([
            'course_id' => $course->id,
            'class_group_id' => $classGroup->id,
            'faculty_id' => $faculty->id,
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::Monday->value,
            'start_time' => '11:00',
            'end_time' => '09:00',
            'effective_from' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['end_time']);
});

test('effective_from_is_required', function () {
    $admin = adminForTimetable();
    $course = Course::factory()->create();
    $classGroup = ClassGroup::factory()->create(['course_id' => $course->id]);
    $faculty = Faculty::factory()->create();
    $room = Room::factory()->create();

    $this->actingAs($admin);

    livewire(CreateTimetable::class)
        ->fillForm([
            'course_id' => $course->id,
            'class_group_id' => $classGroup->id,
            'faculty_id' => $faculty->id,
            'room_id' => $room->id,
            'day_of_week' => DayOfWeek::Monday->value,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'effective_from' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['effective_from']);
});
