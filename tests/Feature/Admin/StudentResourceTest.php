<?php

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\Students\Pages\CreateStudent;
use App\Filament\Admin\Resources\Students\Pages\EditStudent;
use App\Filament\Admin\Resources\Students\Pages\ListStudents;
use App\Models\AdminRoleAssignment;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// Helper: create an admin user with an active role assignment for a given department.
function adminForDepartment(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_list_students_in_own_department', function () {
    $dept = Department::factory()->create();
    $admin = adminForDepartment($dept);

    $students = Student::factory()->count(3)->create(['department_id' => $dept->id]);

    $this->actingAs($admin);

    livewire(ListStudents::class)
        ->assertCanSeeTableRecords($students);
});

test('admin_cannot_see_students_from_other_departments', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $admin = adminForDepartment($dept);

    $ownStudents = Student::factory()->count(2)->create(['department_id' => $dept->id]);
    $otherStudents = Student::factory()->count(2)->create(['department_id' => $otherDept->id]);

    $this->actingAs($admin);

    livewire(ListStudents::class)
        ->assertCanSeeTableRecords($ownStudents)
        ->assertCanNotSeeTableRecords($otherStudents);
});

test('admin_can_create_student', function () {
    $dept = Department::factory()->create();
    $admin = adminForDepartment($dept);
    $user = User::factory()->student()->create();

    $this->actingAs($admin);

    livewire(CreateStudent::class)
        ->fillForm([
            'user_id' => $user->id,
            'department_id' => $dept->id,
            'roll_no' => '2024001',
            'batch_year' => '2024',
            'section' => 'A',
            'status' => StudentStatus::Active->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(Student::class, [
        'user_id' => $user->id,
        'roll_no' => '2024001',
        'batch_year' => '2024',
    ]);
});

test('admin_can_edit_student', function () {
    $dept = Department::factory()->create();
    $admin = adminForDepartment($dept);
    $student = Student::factory()->create(['department_id' => $dept->id, 'section' => 'A']);

    $this->actingAs($admin);

    livewire(EditStudent::class, ['record' => $student->id])
        ->fillForm(['section' => 'B'])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(Student::class, ['id' => $student->id, 'section' => 'B']);
});

test('roll_no_must_be_unique', function () {
    $dept = Department::factory()->create();
    $admin = adminForDepartment($dept);
    $user = User::factory()->student()->create();
    Student::factory()->create(['department_id' => $dept->id, 'roll_no' => '2024999']);

    $this->actingAs($admin);

    livewire(CreateStudent::class)
        ->fillForm([
            'user_id' => $user->id,
            'department_id' => $dept->id,
            'roll_no' => '2024999',
            'batch_year' => '2024',
            'section' => 'A',
            'status' => StudentStatus::Active->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['roll_no']);
});

test('bulk_enroll_creates_enrollment_records', function () {
    $dept = Department::factory()->create();
    $admin = adminForDepartment($dept);
    $course = Course::factory()->create(['department_id' => $dept->id]);
    $group = ClassGroup::factory()->create(['course_id' => $course->id]);
    $students = Student::factory()->count(3)->create(['department_id' => $dept->id]);

    $this->actingAs($admin);

    livewire(ListStudents::class)
        ->callTableBulkAction('enroll_in_course', $students, data: [
            'course_id' => $course->id,
            'class_group_id' => $group->id,
        ])
        ->assertNotified();

    foreach ($students as $student) {
        \Pest\Laravel\assertDatabaseHas(Enrollment::class, [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'class_group_id' => $group->id,
            'status' => EnrollmentStatus::Active->value,
        ]);
    }
});
