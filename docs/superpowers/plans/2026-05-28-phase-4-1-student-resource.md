# Phase 4.1 — StudentResource Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `StudentResource` in the Admin panel with department-scoped listing, full CRUD, CSV export, and a bulk enrol-in-course action.

**Architecture:** Follows the SuperAdmin panel's resource layout — each resource lives in its own subdirectory (`Students/`) with separate `Pages/`, `Schemas/`, and `Tables/` classes. The resource's `getEloquentQuery()` scopes rows to the authenticated admin's department via `activeAdminAssignment`. The bulk enrol action writes `Enrollment` rows directly inside the action closure.

**Tech Stack:** Filament v4, Laravel 12, Pest 3, `StudentStatus` / `EnrollmentStatus` enums, `Student` / `Enrollment` / `Course` / `ClassGroup` / `Department` / `User` models.

---

### Task 1: Scaffold directory structure and write the failing tests

**Files:**
- Create: `tests/Feature/Admin/StudentResourceTest.php`

- [ ] **Step 1: Create the test file**

```php
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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

// Helper: create an admin user with an active role assignment for a given department.
function adminForDepartment(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id'       => $admin->id,
        'department_id' => $department->id,
        'revoked_at'    => null,
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
    $dept      = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $admin     = adminForDepartment($dept);

    $ownStudents   = Student::factory()->count(2)->create(['department_id' => $dept->id]);
    $otherStudents = Student::factory()->count(2)->create(['department_id' => $otherDept->id]);

    $this->actingAs($admin);

    livewire(ListStudents::class)
        ->assertCanSeeTableRecords($ownStudents)
        ->assertCanNotSeeTableRecords($otherStudents);
});

test('admin_can_create_student', function () {
    $dept  = Department::factory()->create();
    $admin = adminForDepartment($dept);
    $user  = User::factory()->student()->create();

    $this->actingAs($admin);

    livewire(CreateStudent::class)
        ->fillForm([
            'user_id'       => $user->id,
            'department_id' => $dept->id,
            'roll_no'       => '2024001',
            'batch_year'    => '2024',
            'section'       => 'A',
            'status'        => StudentStatus::Active->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(Student::class, [
        'user_id'    => $user->id,
        'roll_no'    => '2024001',
        'batch_year' => '2024',
    ]);
});

test('admin_can_edit_student', function () {
    $dept    = Department::factory()->create();
    $admin   = adminForDepartment($dept);
    $student = Student::factory()->create(['department_id' => $dept->id, 'section' => 'A']);

    $this->actingAs($admin);

    livewire(EditStudent::class, ['record' => $student->id])
        ->fillForm(['section' => 'B'])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(Student::class, ['id' => $student->id, 'section' => 'B']);
});

test('roll_no_must_be_unique', function () {
    $dept    = Department::factory()->create();
    $admin   = adminForDepartment($dept);
    $user    = User::factory()->student()->create();
    Student::factory()->create(['department_id' => $dept->id, 'roll_no' => '2024999']);

    $this->actingAs($admin);

    livewire(CreateStudent::class)
        ->fillForm([
            'user_id'       => $user->id,
            'department_id' => $dept->id,
            'roll_no'       => '2024999',
            'batch_year'    => '2024',
            'section'       => 'A',
            'status'        => StudentStatus::Active->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['roll_no']);
});

test('bulk_enroll_creates_enrollment_records', function () {
    $dept     = Department::factory()->create();
    $admin    = adminForDepartment($dept);
    $course   = Course::factory()->create(['department_id' => $dept->id]);
    $group    = ClassGroup::factory()->create(['course_id' => $course->id]);
    $students = Student::factory()->count(3)->create(['department_id' => $dept->id]);

    $this->actingAs($admin);

    livewire(ListStudents::class)
        ->callBulkAction('enroll_in_course', $students, [
            'course_id'      => $course->id,
            'class_group_id' => $group->id,
        ])
        ->assertSuccessful();

    foreach ($students as $student) {
        \Pest\Laravel\assertDatabaseHas(Enrollment::class, [
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'class_group_id' => $group->id,
            'status'         => EnrollmentStatus::Active->value,
        ]);
    }
});
```

- [ ] **Step 2: Run tests — expect failure because the resource classes don't exist yet**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=Admin/StudentResourceTest
```

Expected: errors about missing classes (`ListStudents`, `CreateStudent`, `EditStudent`).

---

### Task 2: Create the Pages

**Files:**
- Create: `app/Filament/Admin/Resources/Students/Pages/ListStudents.php`
- Create: `app/Filament/Admin/Resources/Students/Pages/CreateStudent.php`
- Create: `app/Filament/Admin/Resources/Students/Pages/EditStudent.php`

- [ ] **Step 1: Create `ListStudents.php`**

```php
<?php

namespace App\Filament\Admin\Resources\Students\Pages;

use App\Filament\Admin\Resources\Students\StudentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

- [ ] **Step 2: Create `CreateStudent.php`**

```php
<?php

namespace App\Filament\Admin\Resources\Students\Pages;

use App\Filament\Admin\Resources\Students\StudentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;
}
```

- [ ] **Step 3: Create `EditStudent.php`**

```php
<?php

namespace App\Filament\Admin\Resources\Students\Pages;

use App\Filament\Admin\Resources\Students\StudentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

---

### Task 3: Create the Form schema

**Files:**
- Create: `app/Filament/Admin/Resources/Students/Schemas/StudentForm.php`

- [ ] **Step 1: Create `StudentForm.php`**

```php
<?php

namespace App\Filament\Admin\Resources\Students\Schemas;

use App\Enums\StudentStatus;
use App\Models\Department;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('roll_no')
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            Select::make('user_id')
                ->label('User')
                ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload(false)
                ->required(),
            Select::make('department_id')
                ->label('Department')
                ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            TextInput::make('batch_year')
                ->required()
                ->maxLength(4),
            TextInput::make('section')
                ->maxLength(10),
            Select::make('status')
                ->options(StudentStatus::class)
                ->default(StudentStatus::Active->value)
                ->required(),
        ]);
    }
}
```

---

### Task 4: Create the Table definition

**Files:**
- Create: `app/Filament/Admin/Resources/Students/Tables/StudentsTable.php`

- [ ] **Step 1: Create `StudentsTable.php`**

```php
<?php

namespace App\Filament\Admin\Resources\Students\Tables;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('roll_no')
                    ->label('Roll No')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable(),
                TextColumn::make('batch_year')
                    ->label('Batch')
                    ->sortable(),
                TextColumn::make('section')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->options(fn () => Department::query()->orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('status')
                    ->options(StudentStatus::class),
                SelectFilter::make('batch_year')
                    ->label('Batch Year')
                    ->options(fn () => Student::query()
                        ->distinct()
                        ->orderByDesc('batch_year')
                        ->pluck('batch_year', 'batch_year')
                        ->toArray()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('viewAttendance')
                    ->label('Attendance')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->url(fn (Student $record) => '/admin/attendance-records?tableFilters%5Bstudent_id%5D%5Bvalue%5D='.$record->id),
                Action::make('viewEnrollments')
                    ->label('Enrollments')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->url(fn (Student $record) => '/admin/enrollments?tableFilters%5Bstudent_id%5D%5Bvalue%5D='.$record->id),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(function (Collection $records) {
                            $handle = fopen('php://temp', 'r+');
                            fputcsv($handle, ['Roll No', 'Name', 'Department', 'Batch Year', 'Section', 'Status']);
                            foreach ($records as $record) {
                                /** @var Student&Model $record */
                                fputcsv($handle, [
                                    $record->roll_no,
                                    $record->user?->name ?? '—',
                                    $record->department?->name ?? '—',
                                    $record->batch_year,
                                    $record->section,
                                    $record->status->value,
                                ]);
                            }
                            rewind($handle);
                            $csv = stream_get_contents($handle);
                            fclose($handle);

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, 'students.csv');
                        }),
                    BulkAction::make('enroll_in_course')
                        ->label('Enrol in Course')
                        ->icon(Heroicon::OutlinedAcademicCap)
                        ->schema([
                            Select::make('course_id')
                                ->label('Course')
                                ->options(fn () => Course::query()->orderBy('name')->pluck('name', 'id'))
                                ->searchable()
                                ->required()
                                ->live(),
                            Select::make('class_group_id')
                                ->label('Class Group')
                                ->options(fn (\Filament\Schemas\Components\Utilities\Get $get) => ClassGroup::query()
                                    ->where('course_id', $get('course_id'))
                                    ->orderBy('name')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $student) {
                                Enrollment::firstOrCreate(
                                    [
                                        'student_id' => $student->id,
                                        'course_id'  => $data['course_id'],
                                    ],
                                    [
                                        'class_group_id' => $data['class_group_id'],
                                        'status'         => EnrollmentStatus::Active,
                                        'enrolled_at'    => now(),
                                    ]
                                );
                            }
                            Notification::make()->title('Students enrolled successfully')->success()->send();
                        }),
                ]),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No students found')
            ->emptyStateDescription('Add the first student using the button above.');
    }
}
```

---

### Task 5: Create the Resource class

**Files:**
- Create: `app/Filament/Admin/Resources/Students/StudentResource.php`

- [ ] **Step 1: Create `StudentResource.php`**

```php
<?php

namespace App\Filament\Admin\Resources\Students;

use App\Filament\Admin\Resources\Students\Pages\CreateStudent;
use App\Filament\Admin\Resources\Students\Pages\EditStudent;
use App\Filament\Admin\Resources\Students\Pages\ListStudents;
use App\Filament\Admin\Resources\Students\Schemas\StudentForm;
use App\Filament\Admin\Resources\Students\Tables\StudentsTable;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Academic Management';

    public static function getEloquentQuery(): Builder
    {
        $departmentId = auth()->user()?->activeAdminAssignment?->department_id;

        return parent::getEloquentQuery()
            ->with(['user', 'department'])
            ->when(
                $departmentId,
                fn (Builder $query) => $query->where('department_id', $departmentId)
            );
    }

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'edit'   => EditStudent::route('/{record}/edit'),
        ];
    }
}
```

---

### Task 6: Run the full test suite and fix issues

- [ ] **Step 1: Run all Admin StudentResource tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=Admin/StudentResourceTest
```

Expected: all 6 tests pass.

- [ ] **Step 2: Run Pint to fix any code style issues**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Run the full test suite to confirm no regressions**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
```

Expected: all tests pass (or only previously-failing tests fail).

- [ ] **Step 4: Commit**

```bash
git add \
  app/Filament/Admin/Resources/Students/ \
  tests/Feature/Admin/StudentResourceTest.php
git commit -m "feat: add Admin StudentResource (Phase 4.1)"
```

---

### Task 7: Mark Phase 4.1 complete in project phases doc

**Files:**
- Modify: `docs/project-phases.md`

- [ ] **Step 1: Update status marker for Phase 4.1**

Change `### Phase 4.1 — StudentResource ⬜` to `### Phase 4.1 — StudentResource ✅` in `docs/project-phases.md`.

- [ ] **Step 2: Update summary table**

In the summary table at the bottom, change the Phase 4 row from `⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜` to `✅⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜`.

- [ ] **Step 3: Commit**

```bash
git add docs/project-phases.md
git commit -m "docs: mark Phase 4.1 complete"
```
