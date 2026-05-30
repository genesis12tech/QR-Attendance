<?php

use App\Enums\FacultyStatus;
use App\Filament\Admin\Resources\Faculty\Pages\CreateFaculty;
use App\Filament\Admin\Resources\Faculty\Pages\ListFaculty;
use App\Models\AdminRoleAssignment;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForFacultyDept(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_list_faculty_in_own_department', function () {
    $dept = Department::factory()->create();
    $admin = adminForFacultyDept($dept);

    $faculty = Faculty::factory()->count(3)->create(['department_id' => $dept->id]);

    $this->actingAs($admin);

    livewire(ListFaculty::class)
        ->assertCanSeeTableRecords($faculty);
});

test('admin_cannot_see_faculty_from_other_departments', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $admin = adminForFacultyDept($dept);

    $ownFaculty = Faculty::factory()->count(2)->create(['department_id' => $dept->id]);
    $otherFaculty = Faculty::factory()->count(2)->create(['department_id' => $otherDept->id]);

    $this->actingAs($admin);

    livewire(ListFaculty::class)
        ->assertCanSeeTableRecords($ownFaculty)
        ->assertCanNotSeeTableRecords($otherFaculty);
});

test('admin_can_create_faculty', function () {
    $dept = Department::factory()->create();
    $admin = adminForFacultyDept($dept);
    $user = User::factory()->faculty()->create();

    $this->actingAs($admin);

    livewire(CreateFaculty::class)
        ->fillForm([
            'user_id' => $user->id,
            'department_id' => $dept->id,
            'employee_code' => 'EMP-1234',
            'designation' => 'Senior Lecturer',
            'status' => FacultyStatus::Active->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(Faculty::class, [
        'user_id' => $user->id,
        'employee_code' => 'EMP-1234',
        'designation' => 'Senior Lecturer',
    ]);
});

test('employee_code_must_be_unique', function () {
    $dept = Department::factory()->create();
    $admin = adminForFacultyDept($dept);
    $user = User::factory()->faculty()->create();
    Faculty::factory()->create(['department_id' => $dept->id, 'employee_code' => 'EMP-9999']);

    $this->actingAs($admin);

    livewire(CreateFaculty::class)
        ->fillForm([
            'user_id' => $user->id,
            'department_id' => $dept->id,
            'employee_code' => 'EMP-9999',
            'designation' => 'Lecturer',
            'status' => FacultyStatus::Active->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['employee_code']);
});
