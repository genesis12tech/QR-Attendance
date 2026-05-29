<?php

use App\Filament\SuperAdmin\Resources\Departments\Pages\CreateDepartment;
use App\Filament\SuperAdmin\Resources\Departments\Pages\EditDepartment;
use App\Filament\SuperAdmin\Resources\Departments\Pages\ListDepartments;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_departments', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $departments = Department::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListDepartments::class)
        ->assertSee($departments->first()->name)
        ->assertSee($departments->last()->name);
});

test('super_admin_can_create_department', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(CreateDepartment::class)
        ->fillForm(['name' => 'Computer Science', 'code' => 'CS', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(Department::class, ['name' => 'Computer Science', 'code' => 'CS']);
});

test('super_admin_can_edit_department', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create(['name' => 'Old Name']);

    $this->actingAs($superAdmin);

    livewire(EditDepartment::class, ['record' => $dept->id])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(Department::class, ['id' => $dept->id, 'name' => 'New Name']);
});

test('super_admin_can_delete_department', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create();

    $this->actingAs($superAdmin);

    livewire(ListDepartments::class)
        ->callAction(TestAction::make('delete')->table($dept))
        ->assertNotified();

    \Pest\Laravel\assertDatabaseMissing(Department::class, ['id' => $dept->id]);
});

test('department_name_is_required', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(CreateDepartment::class)
        ->fillForm(['name' => null, 'code' => 'CS'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

test('department_code_must_be_unique', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Department::factory()->create(['code' => 'CS']);

    $this->actingAs($superAdmin);

    livewire(CreateDepartment::class)
        ->fillForm(['name' => 'Computer Science 2', 'code' => 'CS'])
        ->call('create')
        ->assertHasFormErrors(['code']);
});
