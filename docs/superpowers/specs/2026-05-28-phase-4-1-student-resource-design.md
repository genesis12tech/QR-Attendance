# Phase 4.1 — StudentResource Design Spec

**Date:** 2026-05-28  
**Panel:** Admin (`/admin`)  
**Model:** `App\Models\Student`

---

## Overview

A Filament 4 resource for department-scoped student management in the Admin panel. Admins can list, create, edit, export, and bulk-enrol students, but only within their own department.

---

## File Structure

Follows the existing SuperAdmin resource pattern (separate Schemas/Tables classes):

```
app/Filament/Admin/Resources/Students/
  StudentResource.php
  Pages/
    ListStudents.php
    CreateStudent.php
    EditStudent.php
  Schemas/
    StudentForm.php
  Tables/
    StudentsTable.php
```

---

## Resource (`StudentResource`)

- `$model = Student::class`
- `$navigationGroup = 'Academic Management'`
- `$navigationIcon = Heroicon::OutlinedAcademicCap`
- `$defaultPaginationPageOption = 25`
- `getEloquentQuery()`: chains `where('department_id', auth()->user()->activeAdminAssignment?->department_id)` onto the parent query. Returns an empty result set when the assignment is null (no exception).
- Pages: `index`, `create`, `edit`

---

## Form (`StudentForm`)

| Field | Component | Notes |
|---|---|---|
| `roll_no` | `TextInput` | required, `->unique(ignoreRecord: true)` |
| `user_id` | `Select` | `->relationship('user', 'name')`, searchable, lazy |
| `department_id` | `Select` | `->relationship('department', 'name')` |
| `batch_year` | `TextInput` | numeric |
| `section` | `TextInput` | nullable |
| `status` | `Select` | options from `StudentStatus` enum |

---

## Table (`StudentsTable`)

**Columns:** `roll_no` (sortable, searchable), `user.name` (label: Name), `department.name` (label: Department), `batch_year`, `section`, `status` (badge).

**Filters:**
- `SelectFilter(department_id)` → relationship `department`, `name`
- `SelectFilter(status)` → options from `StudentStatus`
- `SelectFilter(batch_year)` → distinct batch years

**Record actions:**
- `EditAction`
- `Action('viewAttendance')` — redirects to `AttendanceRecordResource` URL filtered by `student_id` (implemented via `->url()`; routes to Phase 4.8 resource path)
- `Action('viewEnrollments')` — redirects to `EnrollmentResource` URL filtered by `student_id` (routes to Phase 4.6 resource path)

**Bulk actions:**
- `BulkAction('export_csv')` — streams CSV with columns: Roll No, Name, Department, Batch Year, Section, Status. Follows inline pattern from `AdminUsersTable`.
- `BulkAction('enroll_in_course')` — modal form with `Select(course_id, searchable)` and `Select(class_group_id)`. On submit: creates `Enrollment` rows for each selected student (skips duplicates via `firstOrCreate`). Shows success notification.

---

## Department Scoping

`auth()->user()->activeAdminAssignment?->department_id` — the `activeAdminAssignment` `HasOne` on `User` returns the most recent non-revoked `AdminRoleAssignment`, which carries `department_id`. No alias is added to `User`.

---

## Redirect Actions (Forward-Compatible)

`ViewAttendanceAction` and `ViewEnrollmentsAction` generate URLs to resource paths that will exist after Phases 4.6 and 4.8. Until those phases are built the links 404, but no further changes will be needed once the resources are registered.

---

## Tests (`tests/Feature/Admin/StudentResourceTest.php`)

| Test | What it verifies |
|---|---|
| `test_admin_can_list_students_in_own_department` | Admin sees only own-department students |
| `test_admin_cannot_see_students_from_other_departments` | Cross-department isolation |
| `test_admin_can_create_student` | Create flow, DB row inserted |
| `test_admin_can_edit_student` | Edit flow, DB row updated |
| `test_roll_no_must_be_unique` | Validation rejects duplicate roll_no |
| `test_bulk_enroll_creates_enrollment_records` | Bulk action creates `Enrollment` rows |

All tests use `LazilyRefreshDatabase`, act as an admin user with an `AdminRoleAssignment`, and use factories.
