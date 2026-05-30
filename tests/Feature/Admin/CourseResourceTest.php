<?php

use App\Filament\Admin\Resources\Courses\Pages\CreateCourse;
use App\Filament\Admin\Resources\Courses\Pages\ListCourses;
use App\Models\AdminRoleAssignment;
use App\Models\Course;
use App\Models\Department;
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

function adminForCourse(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_list_courses', function () {
    $dept = Department::factory()->create();
    $admin = adminForCourse($dept);
    $courses = Course::factory()->count(3)->create(['department_id' => $dept->id]);

    $this->actingAs($admin);

    livewire(ListCourses::class)
        ->assertCanSeeTableRecords($courses);
});

test('admin_can_create_course', function () {
    $dept = Department::factory()->create();
    $admin = adminForCourse($dept);

    $this->actingAs($admin);

    livewire(CreateCourse::class)
        ->fillForm([
            'department_id' => $dept->id,
            'code' => 'CS-100',
            'name' => 'Introduction to CS',
            'semester' => '1',
            'credits' => 3,
            'min_attendance_pct' => 75,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Course::class, [
        'code' => 'CS-100',
        'name' => 'Introduction to CS',
    ]);
});

test('course_code_must_be_unique', function () {
    $dept = Department::factory()->create();
    $admin = adminForCourse($dept);
    Course::factory()->create(['department_id' => $dept->id, 'code' => 'CS-100']);

    $this->actingAs($admin);

    livewire(CreateCourse::class)
        ->fillForm([
            'department_id' => $dept->id,
            'code' => 'CS-100',
            'name' => 'Another Course',
            'semester' => '2',
            'credits' => 3,
            'min_attendance_pct' => 75,
        ])
        ->call('create')
        ->assertHasFormErrors(['code']);
});

test('min_attendance_pct_must_be_between_0_and_100', function () {
    $dept = Department::factory()->create();
    $admin = adminForCourse($dept);

    $this->actingAs($admin);

    livewire(CreateCourse::class)
        ->fillForm([
            'department_id' => $dept->id,
            'code' => 'CS-101',
            'name' => 'Test Course',
            'semester' => '1',
            'credits' => 3,
            'min_attendance_pct' => 150,
        ])
        ->call('create')
        ->assertHasFormErrors(['min_attendance_pct']);
});

test('soft_deleted_course_is_not_visible', function () {
    $dept = Department::factory()->create();
    $admin = adminForCourse($dept);
    $deletedCourse = Course::factory()->softDeleted()->create(['department_id' => $dept->id]);

    $this->actingAs($admin);

    livewire(ListCourses::class)
        ->assertCanNotSeeTableRecords(collect([$deletedCourse]));
});
