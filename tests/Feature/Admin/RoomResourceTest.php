<?php

use App\Filament\Admin\Resources\Rooms\Pages\CreateRoom;
use App\Filament\Admin\Resources\Rooms\Pages\EditRoom;
use App\Filament\Admin\Resources\Rooms\Pages\ListRooms;
use App\Models\AdminRoleAssignment;
use App\Models\Department;
use App\Models\Room;
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

function adminForRoom(): User
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

test('admin_can_list_rooms', function () {
    $admin = adminForRoom();
    $rooms = Room::factory()->count(3)->create();

    $this->actingAs($admin);

    livewire(ListRooms::class)
        ->assertCanSeeTableRecords($rooms);
});

test('admin_can_create_room', function () {
    $admin = adminForRoom();

    $this->actingAs($admin);

    livewire(CreateRoom::class)
        ->fillForm([
            'name' => 'Lab 101',
            'building' => 'Science Block',
            'capacity' => 40,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Room::class, [
        'name' => 'Lab 101',
        'building' => 'Science Block',
        'capacity' => 40,
    ]);
});

test('admin_can_toggle_room_active_status', function () {
    $admin = adminForRoom();
    $room = Room::factory()->create(['is_active' => true]);

    $this->actingAs($admin);

    livewire(EditRoom::class, ['record' => $room->id])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Room::class, [
        'id' => $room->id,
        'is_active' => false,
    ]);
});

test('latitude_and_longitude_are_optional', function () {
    $admin = adminForRoom();

    $this->actingAs($admin);

    livewire(CreateRoom::class)
        ->fillForm([
            'name' => 'Room Without Geo',
            'latitude' => null,
            'longitude' => null,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Room::class, [
        'name' => 'Room Without Geo',
        'latitude' => null,
        'longitude' => null,
    ]);
});
