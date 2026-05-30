<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Admin\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Admin\Resources\Enrollments\Pages\ListEnrollments;
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

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForEnrollment(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_list_enrollments', function () {
    $dept = Department::factory()->create();
    $admin = adminForEnrollment($dept);
    $course = Course::factory()->create(['department_id' => $dept->id]);
    $group = ClassGroup::factory()->create(['course_id' => $course->id]);
    $enrollments = Enrollment::factory()->count(3)->create([
        'course_id' => $course->id,
        'class_group_id' => $group->id,
    ]);

    $this->actingAs($admin);

    livewire(ListEnrollments::class)
        ->assertCanSeeTableRecords($enrollments);
});

test('admin_can_create_enrollment', function () {
    $dept = Department::factory()->create();
    $admin = adminForEnrollment($dept);
    $student = Student::factory()->create(['department_id' => $dept->id]);
    $course = Course::factory()->create(['department_id' => $dept->id]);
    $group = ClassGroup::factory()->create(['course_id' => $course->id]);

    $this->actingAs($admin);

    livewire(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'class_group_id' => $group->id,
            'enrolled_at' => now()->toDateString(),
            'status' => EnrollmentStatus::Active->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    assertDatabaseHas(Enrollment::class, [
        'student_id' => $student->id,
        'course_id' => $course->id,
        'class_group_id' => $group->id,
        'status' => EnrollmentStatus::Active->value,
    ]);
});

test('student_cannot_be_enrolled_in_same_course_twice', function () {
    $dept = Department::factory()->create();
    $admin = adminForEnrollment($dept);
    $student = Student::factory()->create(['department_id' => $dept->id]);
    $course = Course::factory()->create(['department_id' => $dept->id]);
    $group = ClassGroup::factory()->create(['course_id' => $course->id]);

    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'class_group_id' => $group->id,
    ]);

    $this->actingAs($admin);

    livewire(CreateEnrollment::class)
        ->fillForm([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'class_group_id' => $group->id,
            'enrolled_at' => now()->toDateString(),
            'status' => EnrollmentStatus::Active->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['student_id']);
});

test('bulk_drop_sets_status_to_dropped', function () {
    $dept = Department::factory()->create();
    $admin = adminForEnrollment($dept);
    $course = Course::factory()->create(['department_id' => $dept->id]);
    $group = ClassGroup::factory()->create(['course_id' => $course->id]);
    $enrollments = Enrollment::factory()->count(3)->create([
        'course_id' => $course->id,
        'class_group_id' => $group->id,
        'status' => EnrollmentStatus::Active,
    ]);

    $this->actingAs($admin);

    livewire(ListEnrollments::class)
        ->callTableBulkAction('drop', $enrollments)
        ->assertNotified();

    foreach ($enrollments as $enrollment) {
        assertDatabaseHas(Enrollment::class, [
            'id' => $enrollment->id,
            'status' => EnrollmentStatus::Dropped->value,
        ]);
    }
});

test('bulk_mark_completed_sets_status_to_completed', function () {
    $dept = Department::factory()->create();
    $admin = adminForEnrollment($dept);
    $course = Course::factory()->create(['department_id' => $dept->id]);
    $group = ClassGroup::factory()->create(['course_id' => $course->id]);
    $enrollments = Enrollment::factory()->count(3)->create([
        'course_id' => $course->id,
        'class_group_id' => $group->id,
        'status' => EnrollmentStatus::Active,
    ]);

    $this->actingAs($admin);

    livewire(ListEnrollments::class)
        ->callTableBulkAction('mark_completed', $enrollments)
        ->assertNotified();

    foreach ($enrollments as $enrollment) {
        assertDatabaseHas(Enrollment::class, [
            'id' => $enrollment->id,
            'status' => EnrollmentStatus::Completed->value,
        ]);
    }
});
