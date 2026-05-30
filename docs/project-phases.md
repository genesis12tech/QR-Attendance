
# Project Phases — Secure Dynamic QR Attendance System

## Status Legend
- ✅ Complete
- 🔧 Partial (exists but needs rework)
- ⬜ Not started

Tasks marked with tests list the Pest feature/unit test functions to be generated as acceptance criteria.

---

## Phase 1: DB Structure

### Phase 1.1 — Project & Package Setup ✅

Laravel 12 project initialised. All required packages installed:
`filament/filament ^4.0`, `laravel/reverb ^1.0`, `laravel/sanctum ^4.0`,
`barryvdh/laravel-dompdf ^3.0`, `maatwebsite/excel ^3.1`,
`simplesoftwareio/simple-qrcode ^4.2`, `pestphp/pest ^3.0`,
`pestphp/pest-plugin-laravel ^3.0`.

`spatie/laravel-medialibrary` must be installed before Phase 1.3 runs.

### Phase 1.2 — Base Database Migrations ✅

All 9 domain migrations created. Tables:
`users` (extended), `departments`, `students`, `faculty`, `security_policies`,
`system_settings`, `data_retention_policies`, `admin_role_assignments`,
`courses`, `class_groups`, `rooms`, `timetables`, `enrollments`,
`device_registrations`, `attendance_sessions`, `attendance_records`,
`session_exports`, `proxy_flags`, `audit_logs`.

**Note:** These migrations contain `enum` columns that must be amended in Phase 1.3.
`timetables.day_of_week` is intentionally left as `enum` — its values are permanently fixed.

### Phase 1.3 — Schema Amendment & Media Library ✅

**Install `spatie/laravel-medialibrary`:**

```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

**Create one amendment migration** (`amend_enum_columns_to_string`) that converts every
`enum` column (except `timetables.day_of_week`) to `string` with a `->comment()` documenting
the expected values. Each comment serves as documentation until a PHP Enum is applied at the
model level.

Columns to amend:

| Table | Column | Comment (expected values) |
|---|---|---|
| `users` | `role` | `super_admin,admin,faculty,student` |
| `users` | `status` | `active,suspended,inactive` |
| `students` | `status` | `active,suspended,graduated,dropped` |
| `faculty` | `status` | `active,on_leave,inactive` |
| `admin_role_assignments` | `role` | `admin,faculty` |
| `enrollments` | `status` | `active,dropped,completed` |
| `attendance_sessions` | `status` | `pending,active,paused,closed` |
| `attendance_records` | `status` | `present,late,absent,pending_review,rejected` |
| `session_exports` | `format` | `pdf,csv,xlsx` |
| `session_exports` | `status` | `pending,processing,ready,failed` |
| `proxy_flags` | `severity` | `low,medium,high,critical` |
| `proxy_flags` | `review_status` | `pending,approved,rejected` |

`timetables.day_of_week` remains `enum` — the 7 day values will never change.

**Tests:** `tests/Feature/Migrations/SchemaAmendmentTest.php`
- `test_users_role_column_is_string_type()`
- `test_users_status_column_is_string_type()`
- `test_timetables_day_of_week_remains_enum()`

### Phase 1.4 — PHP Enum Classes ✅

Create one backed string enum per domain concept in `app/Enums/`.

| File | Cases |
|---|---|
| `UserRole.php` | `SuperAdmin='super_admin'`, `Admin='admin'`, `Faculty='faculty'`, `Student='student'` |
| `UserStatus.php` | `Active='active'`, `Suspended='suspended'`, `Inactive='inactive'` |
| `StudentStatus.php` | `Active='active'`, `Suspended='suspended'`, `Graduated='graduated'`, `Dropped='dropped'` |
| `FacultyStatus.php` | `Active='active'`, `OnLeave='on_leave'`, `Inactive='inactive'` |
| `AdminAssignmentRole.php` | `Admin='admin'`, `Faculty='faculty'` |
| `EnrollmentStatus.php` | `Active='active'`, `Dropped='dropped'`, `Completed='completed'` |
| `SessionStatus.php` | `Pending='pending'`, `Active='active'`, `Paused='paused'`, `Closed='closed'` |
| `AttendanceStatus.php` | `Present='present'`, `Late='late'`, `Absent='absent'`, `PendingReview='pending_review'`, `Rejected='rejected'` |
| `ExportFormat.php` | `Pdf='pdf'`, `Csv='csv'`, `Xlsx='xlsx'` |
| `ExportStatus.php` | `Pending='pending'`, `Processing='processing'`, `Ready='ready'`, `Failed='failed'` |
| `ProxySeverity.php` | `Low='low'`, `Medium='medium'`, `High='high'`, `Critical='critical'` |
| `ReviewStatus.php` | `Pending='pending'`, `Approved='approved'`, `Rejected='rejected'` |
| `DayOfWeek.php` | `Monday='monday'` … `Sunday='sunday'` (mirrors the enum in `timetables`) |

**Tests:** `tests/Unit/Enums/EnumTest.php`
- `test_user_role_enum_backed_values_match_expected_strings()`
- `test_user_status_enum_backed_values_match_expected_strings()`
- `test_attendance_status_has_pending_review_case()`
- `test_day_of_week_has_seven_cases()`

### Phase 1.5 — Eloquent Models ✅

Create all models via `php artisan make:model`. Every model must define `$fillable` and a
`casts()` method (not the `$casts` property). Relationships must have return type hints.
`spatie/laravel-medialibrary` must be installed (Phase 1.3) before creating models that
implement `HasMedia`.

---

**`User`** — update existing `app/Models/User.php`

Add to `$fillable`: `role`, `status`, `last_login_at`

```php
protected function casts(): array
{
    return [
        'role'              => UserRole::class,
        'status'            => UserStatus::class,
        'last_login_at'     => 'datetime',
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];
}
```

Relationships:
- `student(): HasOne` → `Student`
- `faculty(): HasOne` → `Faculty`
- `adminRoleAssignments(): HasMany` → `AdminRoleAssignment`
- `activeAdminAssignment(): HasOne` → `AdminRoleAssignment` scoped `whereNull('revoked_at')->latestOfMany('assigned_at')`
- `deviceRegistrations(): HasMany` → `DeviceRegistration`
- `auditLogs(): HasMany` → `AuditLog` (FK: `actor_id`)

---

**`Department`** — `app/Models/Department.php`

`$fillable`: `name`, `code`, `head_faculty_id`, `is_active`

```php
protected function casts(): array
{
    return ['is_active' => 'boolean'];
}
```

Relationships:
- `headFaculty(): BelongsTo` → `Faculty` (FK: `head_faculty_id`)
- `students(): HasMany` → `Student`
- `faculty(): HasMany` → `Faculty`
- `courses(): HasMany` → `Course`

---

**`Student`** — `app/Models/Student.php`

Uses: `SoftDeletes`

`$fillable`: `user_id`, `department_id`, `roll_no`, `batch_year`, `section`, `status`

```php
protected function casts(): array
{
    return ['status' => StudentStatus::class];
}
```

Relationships:
- `user(): BelongsTo` → `User`
- `department(): BelongsTo` → `Department`
- `enrollments(): HasMany` → `Enrollment`
- `attendanceRecords(): HasMany` → `AttendanceRecord`

---

**`Faculty`** — `app/Models/Faculty.php`

`$fillable`: `user_id`, `department_id`, `employee_code`, `designation`, `status`

```php
protected function casts(): array
{
    return ['status' => FacultyStatus::class];
}
```

Relationships:
- `user(): BelongsTo` → `User`
- `department(): BelongsTo` → `Department`
- `timetables(): HasMany` → `Timetable`
- `attendanceSessions(): HasMany` → `AttendanceSession`

---

**`Course`** — `app/Models/Course.php`

Uses: `SoftDeletes`

`$fillable`: `department_id`, `code`, `name`, `semester`, `credits`, `min_attendance_pct`

```php
protected function casts(): array
{
    return ['min_attendance_pct' => 'decimal:2'];
}
```

Relationships:
- `department(): BelongsTo` → `Department`
- `classGroups(): HasMany` → `ClassGroup`
- `enrollments(): HasMany` → `Enrollment`
- `timetables(): HasMany` → `Timetable`
- `attendanceSessions(): HasMany` → `AttendanceSession`

---

**`ClassGroup`** — `app/Models/ClassGroup.php`

`$fillable`: `course_id`, `name`

Relationships:
- `course(): BelongsTo` → `Course`
- `enrollments(): HasMany` → `Enrollment`
- `timetables(): HasMany` → `Timetable`
- `attendanceSessions(): HasMany` → `AttendanceSession`

---

**`Room`** — `app/Models/Room.php`

`$fillable`: `name`, `building`, `capacity`, `latitude`, `longitude`, `geofence_radius_m`,
`beacon_id`, `wifi_ssid`, `is_active`

```php
protected function casts(): array
{
    return [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];
}
```

Relationships:
- `timetables(): HasMany` → `Timetable`
- `attendanceSessions(): HasMany` → `AttendanceSession`

---

**`Timetable`** — `app/Models/Timetable.php`

`$fillable`: `course_id`, `class_group_id`, `faculty_id`, `room_id`, `day_of_week`,
`start_time`, `end_time`, `effective_from`, `effective_until`

```php
protected function casts(): array
{
    return [
        'day_of_week'     => DayOfWeek::class,
        'effective_from'  => 'date',
        'effective_until' => 'date',
    ];
}
```

Relationships:
- `course(): BelongsTo` → `Course`
- `classGroup(): BelongsTo` → `ClassGroup`
- `faculty(): BelongsTo` → `Faculty`
- `room(): BelongsTo` → `Room`

---

**`Enrollment`** — `app/Models/Enrollment.php`

`$fillable`: `student_id`, `course_id`, `class_group_id`, `status`, `enrolled_at`

```php
protected function casts(): array
{
    return [
        'status'      => EnrollmentStatus::class,
        'enrolled_at' => 'datetime',
    ];
}
```

Relationships:
- `student(): BelongsTo` → `Student`
- `course(): BelongsTo` → `Course`
- `classGroup(): BelongsTo` → `ClassGroup`
- `attendanceRecords(): HasMany` → `AttendanceRecord`

---

**`DeviceRegistration`** — `app/Models/DeviceRegistration.php`

`$fillable`: `user_id`, `device_fingerprint`, `device_name`, `platform`, `app_version`,
`is_trusted`, `last_seen_at`

```php
protected function casts(): array
{
    return [
        'is_trusted'   => 'boolean',
        'last_seen_at' => 'datetime',
    ];
}
```

Relationships:
- `user(): BelongsTo` → `User`
- `attendanceRecords(): HasMany` → `AttendanceRecord` (FK: `device_id`)

---

**`AttendanceSession`** — `app/Models/AttendanceSession.php`

`uuid` must be auto-generated on create via the model's `booted()` method:
```php
protected static function booted(): void
{
    static::creating(fn (self $session) => $session->uuid ??= (string) Str::uuid());
}
```

`$fillable`: `faculty_id`, `course_id`, `class_group_id`, `room_id`, `timetable_id`,
`status`, `started_at`, `closed_at`, `close_reason`, `total_enrolled`, `total_present`,
`total_late`, `total_absent`

```php
protected function casts(): array
{
    return [
        'status'     => SessionStatus::class,
        'started_at' => 'datetime',
        'closed_at'  => 'datetime',
    ];
}
```

Relationships:
- `faculty(): BelongsTo` → `Faculty`
- `course(): BelongsTo` → `Course`
- `classGroup(): BelongsTo` → `ClassGroup`
- `room(): BelongsTo` → `Room`
- `timetable(): BelongsTo` → `Timetable`
- `attendanceRecords(): HasMany` → `AttendanceRecord` (FK: `session_id`)
- `sessionExports(): HasMany` → `SessionExport`

---

**`AttendanceRecord`** — `app/Models/AttendanceRecord.php`

Implements `Spatie\MediaLibrary\HasMedia`, uses `Spatie\MediaLibrary\InteractsWithMedia`.
Register a media collection `evidence_files` in `registerMediaCollections()`.

`$fillable`: `session_id`, `student_id`, `enrollment_id`, `status`, `marked_at`, `risk_score`,
`latitude`, `longitude`, `device_id`, `evidence_json`, `override_by`, `override_reason`,
`overridden_at`

```php
protected function casts(): array
{
    return [
        'status'        => AttendanceStatus::class,
        'marked_at'     => 'datetime',
        'latitude'      => 'decimal:7',
        'longitude'     => 'decimal:7',
        'evidence_json' => 'array',
        'overridden_at' => 'datetime',
    ];
}
```

Relationships:
- `session(): BelongsTo` → `AttendanceSession` (FK: `session_id`)
- `student(): BelongsTo` → `Student`
- `enrollment(): BelongsTo` → `Enrollment`
- `device(): BelongsTo` → `DeviceRegistration` (FK: `device_id`)
- `overriddenBy(): BelongsTo` → `User` (FK: `override_by`)
- `proxyFlags(): HasMany` → `ProxyFlag`

---

**`SessionExport`** — `app/Models/SessionExport.php`

`$fillable`: `session_id`, `requested_by`, `format`, `status`, `file_path`, `expires_at`

```php
protected function casts(): array
{
    return [
        'format'     => ExportFormat::class,
        'status'     => ExportStatus::class,
        'expires_at' => 'datetime',
    ];
}
```

Relationships:
- `session(): BelongsTo` → `AttendanceSession` (FK: `session_id`)
- `requestedBy(): BelongsTo` → `User` (FK: `requested_by`)

---

**`SecurityPolicy`** — `app/Models/SecurityPolicy.php`

`$fillable`: `policy_name`, `qr_expiry_seconds`, `risk_auto_reject`, `risk_pending_review`,
`late_threshold_mins`, `geofence_radius_m`, `device_binding_required`, `clock_skew_seconds`,
`is_active`

```php
protected function casts(): array
{
    return [
        'device_binding_required' => 'boolean',
        'is_active'               => 'boolean',
    ];
}
```

Local scope: `active(): Builder` → `where('is_active', true)`

---

**`SystemSetting`** — `app/Models/SystemSetting.php`

`$fillable`: `key`, `value`

Static helpers (caching implementation deferred to Phase 2.3):
- `get(string $key, mixed $default = null): mixed` — finds by `key`, returns `value` or `$default`
- `set(string $key, mixed $value): void` — `updateOrCreate(['key' => $key], ['value' => $value])`

---

**`DataRetentionPolicy`** — `app/Models/DataRetentionPolicy.php`

`$fillable`: `entity_type`, `retention_days`, `is_active`, `last_run_at`

```php
protected function casts(): array
{
    return [
        'is_active'   => 'boolean',
        'last_run_at' => 'datetime',
    ];
}
```

---

**`AdminRoleAssignment`** — `app/Models/AdminRoleAssignment.php`

`$fillable`: `user_id`, `assigned_by`, `role`, `department_id`, `assigned_at`, `revoked_at`

```php
protected function casts(): array
{
    return [
        'role'        => AdminAssignmentRole::class,
        'assigned_at' => 'datetime',
        'revoked_at'  => 'datetime',
    ];
}
```

Relationships:
- `user(): BelongsTo` → `User`
- `assignedBy(): BelongsTo` → `User` (FK: `assigned_by`)
- `department(): BelongsTo` → `Department`

Local scope: `active(): Builder` → `whereNull('revoked_at')`

---

**`ProxyFlag`** — `app/Models/ProxyFlag.php`

Implements `Spatie\MediaLibrary\HasMedia`, uses `Spatie\MediaLibrary\InteractsWithMedia`.
Register a media collection `evidence_files` in `registerMediaCollections()`.

`$fillable`: `attendance_record_id`, `severity`, `reason_code`, `risk_score`, `evidence_json`,
`review_status`, `reviewer_id`, `reviewer_notes`, `reviewed_at`

```php
protected function casts(): array
{
    return [
        'severity'      => ProxySeverity::class,
        'review_status' => ReviewStatus::class,
        'evidence_json' => 'array',
        'reviewed_at'   => 'datetime',
    ];
}
```

Relationships:
- `attendanceRecord(): BelongsTo` → `AttendanceRecord`
- `reviewer(): BelongsTo` → `User` (FK: `reviewer_id`)

Local scope: `pending(): Builder` → `where('review_status', ReviewStatus::Pending)`

---

**`AuditLog`** — `app/Models/AuditLog.php`

`$fillable`: `actor_id`, `actor_role`, `action`, `entity_type`, `entity_id`, `old_values`,
`new_values`, `ip_address`, `user_agent`

```php
protected function casts(): array
{
    return [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
}
```

Relationships:
- `actor(): BelongsTo` → `User` (FK: `actor_id`)

Static helper:
```php
public static function record(
    string $action,
    Model $entity,
    array $oldValues = [],
    array $newValues = [],
    ?User $actor = null,
): self
```
Reads `auth()->user()` when `$actor` is null. Reads `request()->ip()` and
`request()->userAgent()`.

---

**Tests:** `tests/Unit/Models/`
- `test_user_role_cast_returns_user_role_enum()`
- `test_user_status_cast_returns_user_status_enum()`
- `test_user_has_one_student()`
- `test_user_has_one_faculty()`
- `test_department_has_many_students()`
- `test_department_has_many_faculty()`
- `test_department_belongs_to_head_faculty()`
- `test_student_belongs_to_user_and_department()`
- `test_student_soft_deletes()`
- `test_faculty_belongs_to_user_and_department()`
- `test_course_soft_deletes()`
- `test_enrollment_unique_student_course_constraint()`
- `test_attendance_session_uuid_is_auto_generated_on_create()`
- `test_attendance_session_belongs_to_faculty_course_and_class_group()`
- `test_attendance_record_belongs_to_session_and_student()`
- `test_attendance_record_implements_has_media()`
- `test_proxy_flag_implements_has_media()`
- `test_security_policy_active_scope_returns_only_active_rows()`
- `test_admin_role_assignment_active_scope_excludes_revoked()`
- `test_proxy_flag_pending_scope_returns_only_pending_rows()`
- `test_audit_log_record_creates_row_with_correct_fields()`
- `test_system_setting_get_returns_value_by_key()`
- `test_system_setting_get_returns_default_when_key_missing()`
- `test_system_setting_set_creates_row_when_key_does_not_exist()`
- `test_system_setting_set_updates_row_when_key_exists()`

### Phase 1.6 — Model Factories ✅

Create a factory for every model in `database/factories/` via `php artisan make:factory`.
Follow the existing `UserFactory` as the pattern. Use `fake()` (not `$this->faker`).

---

**`UserFactory`** — extend existing with states:
- `superAdmin()`, `admin()`, `faculty()`, `student()` — set `role` to the matching `UserRole` value
- `suspended()` — sets `status = UserStatus::Suspended`

**`DepartmentFactory`**:
- Default: random `name`, unique 3-letter uppercase `code`, `head_faculty_id = null`, `is_active = true`

**`StudentFactory`**:
- Default: `user_id` via `UserFactory::student()`, `department_id` via `DepartmentFactory`
- `roll_no`: year-prefixed padded sequence, e.g. `2024001`
- States: `active()`, `suspended()`, `graduated()`

**`FacultyFactory`**:
- Default: `user_id` via `UserFactory::faculty()`, `department_id` via `DepartmentFactory`
- `employee_code`: e.g. `EMP-` + 4-digit number
- States: `active()`, `onLeave()`

**`CourseFactory`**:
- Default: `department_id` via `DepartmentFactory`, `code` = `CS-` + 3-digit number, `min_attendance_pct = 75.00`
- State: `softDeleted()` — sets `deleted_at = now()`

**`ClassGroupFactory`**:
- Default: `course_id` via `CourseFactory`, `name` = `Group ` + letter (A–E)

**`RoomFactory`**:
- Default: `name`, `building`, `capacity = 40`, no geofence coordinates, `is_active = true`
- State: `withGeofence()` — populates `latitude`, `longitude`, `geofence_radius_m = 50`

**`TimetableFactory`**:
- Default: all FK relationships via respective factories, random `DayOfWeek` case, valid `start_time` / `end_time`

**`EnrollmentFactory`**:
- Default: `StudentFactory`, `CourseFactory`, `ClassGroupFactory`, `status = EnrollmentStatus::Active`
- States: `active()`, `dropped()`, `completed()`

**`DeviceRegistrationFactory`**:
- Default: `UserFactory`, `device_fingerprint` = UUID, `platform = 'android'`, `is_trusted = false`
- State: `trusted()`

**`AttendanceSessionFactory`**:
- Default: `FacultyFactory`, `CourseFactory`, `ClassGroupFactory`, `status = SessionStatus::Pending`
- States: `pending()`, `active()` (sets `started_at`), `closed()` (sets `started_at`, `closed_at`, counters)

**`AttendanceRecordFactory`**:
- Default: `AttendanceSessionFactory::active()`, `StudentFactory`, `status = AttendanceStatus::Present`, `risk_score = 0`
- States: `present()`, `late()`, `pendingReview()`, `rejected()`
- State: `highRisk()` — sets `risk_score` to 80–100

**`SessionExportFactory`**:
- Default: `AttendanceSessionFactory`, `UserFactory`, `format = ExportFormat::Pdf`, `status = ExportStatus::Pending`
- States: `pending()`, `ready()` (sets `file_path`, `expires_at`), `failed()`

**`SecurityPolicyFactory`**:
- Default: matches `DefaultSettingsSeeder` values, `policy_name = 'default'`, `is_active = true`

**`AdminRoleAssignmentFactory`**:
- Default: `UserFactory`, `DepartmentFactory`, `role = AdminAssignmentRole::Admin`, `assigned_at = now()`, `revoked_at = null`

**`ProxyFlagFactory`**:
- Default: `AttendanceRecordFactory::pendingReview()`, `severity = ProxySeverity::Medium`, `review_status = ReviewStatus::Pending`
- States: `pending()`, `critical()` (severity = Critical, risk_score ≥ 80)

**`AuditLogFactory`**:
- Default: `UserFactory`, random `action`, `entity_type`, `entity_id`

**Tests:** `tests/Unit/Factories/FactoryTest.php`
- `test_user_factory_creates_user_with_correct_role_via_state()`
- `test_student_factory_creates_related_user_and_department()`
- `test_attendance_session_factory_active_state_sets_started_at()`
- `test_proxy_flag_factory_critical_state_sets_correct_severity()`

### Phase 1.7 — Seeders ✅

**`DefaultSettingsSeeder`** ✅ — seeds `SecurityPolicy`, `SystemSetting`, and
`DataRetentionPolicy` using `updateOrInsert`.

**`DemoDataSeeder`** ⬜ — for local / staging development only, not run in production.

Creates a deterministic fixture set:
- 1 super admin user
- 2 departments: `Computer Science (CS)`, `Mathematics (MATH)`
- 3 faculty users with `Faculty` profiles; one set as `head_faculty` of CS department
- 20 students spread across both departments with `Student` profiles
- 3 courses (2 in CS, 1 in MATH), each with 2 `ClassGroup` rows
- 1 week of `Timetable` entries for CS courses
- 1 `AttendanceSession` in `closed` state: 10 present + 3 late + 2 pending_review records,
  plus 2 `ProxyFlag` rows
- 1 admin user with an `AdminRoleAssignment` for the CS department
- `AuditLog` rows covering the closed session lifecycle

`DemoDataSeeder` calls `DefaultSettingsSeeder` first. Register it in `DatabaseSeeder::run()`
behind an `App::isLocal()` guard.

**Tests:** none — demo seeders are verified by running and checking row counts in a local DB.

### Phase 1.8 — EnsureRole Middleware ✅

Create `app/Http/Middleware/EnsureRole.php`. Reads `auth()->user()->role` (returns a
`UserRole` enum), compares its value against the parameter passed via
`EnsureRole::class . ':super_admin'`. Returns HTTP 403 if mismatch. Returns HTTP 401 if
unauthenticated.

**Tests:** `tests/Feature/Middleware/EnsureRoleTest.php`
- `test_super_admin_role_passes_super_admin_check()`
- `test_admin_role_is_blocked_from_super_admin_panel()`
- `test_faculty_role_is_blocked_from_admin_panel()`
- `test_unauthenticated_user_receives_401()`
- `test_suspended_user_is_blocked()`

### Phase 1.9 — Filament Panel Providers ✅

Configure all three panel providers. The `SuperAdminPanelProvider` stub needs full
replacement.

**SuperAdminPanelProvider** (`app/Providers/Filament/SuperAdminPanelProvider.php`):
- Path: `super-admin`, colour: `Color::Violet`
- `->authMiddleware([EnsureRole::class . ':super_admin'])`
- `->discoverResources` → `App\Filament\SuperAdmin\Resources`
- `->discoverPages` → `App\Filament\SuperAdmin\Pages`
- `->discoverWidgets` → `App\Filament\SuperAdmin\Widgets`
- Navigation groups: `['Overview', 'Administration', 'Security', 'Audit']`

**AdminPanelProvider** (`app/Providers/Filament/AdminPanelProvider.php`):
- Path: `admin`, colour: `Color::Emerald`
- `->authMiddleware([EnsureRole::class . ':admin'])`
- `->discoverResources` → `App\Filament\Admin\Resources`
- Navigation groups: `['Overview', 'Academic Management', 'Attendance', 'Reports']`
- Navigation badge on Proxy Review: pending proxy flag count

**FacultyPanelProvider** (`app/Providers/Filament/FacultyPanelProvider.php`):
- Path: `faculty`, colour: `Color::Orange`
- `->authMiddleware([EnsureRole::class . ':faculty'])`
- `->discoverResources` → `App\Filament\Faculty\Resources`
- Navigation groups: `['My Sessions', 'My Classes', 'Records']`

**Tests:** `tests/Feature/Panels/PanelAccessTest.php`
- `test_super_admin_can_access_super_admin_panel()`
- `test_admin_is_redirected_from_super_admin_panel()`
- `test_faculty_is_redirected_from_admin_panel()`
- `test_super_admin_is_redirected_from_faculty_panel()`
- `test_unauthenticated_user_is_redirected_to_login()`

---

## Phase 2: Core Services

### Phase 2.1 — QRChallengeService ✅

`app/Services/QRChallengeService.php`

Generates a signed, time-bounded QR payload and stores it in Redis.
On scan, validates the HMAC signature and expiry before accepting.

**Responsibilities:**
- `generateForSession(AttendanceSession $session): string` — creates payload
  `{session_uuid, nonce, issued_at, hmac}`, signs with `QR_SECRET` (HMAC-SHA256),
  encodes as base64 JSON, stores in Redis with TTL = `qr_expiry_seconds`, returns
  base64 PNG QR image string via `simplesoftwareio/simple-qrcode`
- `validateScan(string $payload, AttendanceSession $session): bool` — decodes,
  verifies HMAC, checks `issued_at` within `qr_expiry_seconds + clock_skew_seconds`
- Pulls `SecurityPolicy::active()` for `qr_expiry_seconds` and `clock_skew_seconds`

**Tests:** `tests/Unit/Services/QRChallengeServiceTest.php`
- `test_generate_for_session_returns_base64_png_string()`
- `test_generate_stores_payload_in_redis_with_correct_ttl()`
- `test_validate_scan_returns_true_for_valid_fresh_payload()`
- `test_validate_scan_returns_false_for_expired_payload()`
- `test_validate_scan_returns_false_for_tampered_hmac()`
- `test_validate_scan_returns_false_for_wrong_session()`
- `test_validate_scan_allows_clock_skew_within_policy_tolerance()`

### Phase 2.2 — AuditLog Model & LogsToAudit Trait ✅

`app/Models/AuditLog.php` (static `record()` helper — see Phase 1.4)
`app/Concerns/LogsToAudit.php` — trait mixed into Filament Resource classes

**Trait responsibilities:**
- Provides `logAudit(string $action, Model $entity, array $old, array $new): void`
- Reads `auth()->user()` for actor and role
- Reads request IP and user-agent

**Tests:** `tests/Feature/AuditLogTest.php`
- `test_audit_log_record_persists_to_database()`
- `test_audit_log_captures_actor_id_and_role()`
- `test_audit_log_captures_ip_address()`
- `test_audit_log_stores_old_and_new_values_as_json()`
- `test_audit_log_works_with_null_actor_for_system_actions()`

### Phase 2.3 — SecurityPolicy & SystemSetting Caching ✅

Wrap reads in `Cache::remember()` so polling widgets don't hit the database.

- `SecurityPolicy::active()` → cache key `security_policy.active`, TTL 60s
- `SystemSetting::get($key)` → cache key `system_setting.{$key}`, TTL 60s
- On `SecurityPolicy` save: `Cache::forget('security_policy.active')`
- On `SystemSetting::set($key)`: `Cache::forget("system_setting.{$key}")`

**Tests:** `tests/Unit/Services/CachingTest.php`
- `test_security_policy_active_is_cached_after_first_read()`
- `test_security_policy_cache_is_cleared_on_save()`
- `test_system_setting_get_is_cached_after_first_read()`
- `test_system_setting_cache_is_cleared_on_set()`

### Phase 2.4 — AttendanceMarked Broadcast Event ✅

`app/Events/AttendanceMarked.php`

- Implements `ShouldBroadcast`
- `broadcastOn()` → `PrivateChannel("session.{$session->uuid}")`
- `broadcastAs()` → `'AttendanceMarked'`
- Payload: `{student_name, status, risk_score, marked_at, session_stats}`
- Dispatched inside DB transaction after `AttendanceRecord` is created
- Channel authorisation in `routes/channels.php`:
  faculty owning the session OR super admin / admin of the department

**Tests:** `tests/Feature/Broadcasting/AttendanceMarkedTest.php`
- `test_event_broadcasts_on_correct_private_channel()`
- `test_event_payload_contains_required_fields()`
- `test_faculty_can_listen_to_own_session_channel()`
- `test_faculty_cannot_listen_to_another_facultys_session_channel()`

---

## Phase 3: Super Admin Panel

### Phase 3.1 — DepartmentResource ✅

`app/Filament/SuperAdmin/Resources/DepartmentResource.php`

Table: `name`, `code`, `head_faculty → user → name`, `students_count` (withCount),
`faculty_count` (withCount), `is_active` badge.
Form: `TextInput(name)`, `TextInput(code)`, `Select(head_faculty_id)` lazy, `Toggle(is_active)`.
Actions: `EditAction`, `DeleteAction` (soft), custom `ViewStudentsAction` (redirect to admin panel).
Filters: `SelectFilter(is_active)`.
Empty state declared.

**Tests:** `tests/Feature/SuperAdmin/DepartmentResourceTest.php`
- `test_super_admin_can_list_departments()`
- `test_super_admin_can_create_department()`
- `test_super_admin_can_edit_department()`
- `test_super_admin_can_soft_delete_department()`
- `test_department_name_is_required()`
- `test_department_code_must_be_unique()`

### Phase 3.2 — AdminUserResource ✅

`app/Filament/SuperAdmin/Resources/AdminUserResource.php`

Table: `name`, `email`, `role` badge, `status` badge, `last_login_at`, `department → name`.
Form: `TextInput(name)`, `TextInput(email)`, `TextInput(password confirmed)`,
`Select(role: admin)`, `Select(department_id)`, `Toggle(status)`.
Actions: `EditAction`, `SuspendAction` (sets `status=suspended` + `AuditLog::record()`),
`RevokeRoleAction`.
BulkActions: `SuspendBulkAction`, `ExportCsvBulkAction`.
Header: `CreateAction`.

**Tests:** `tests/Feature/SuperAdmin/AdminUserResourceTest.php`
- `test_super_admin_can_list_admin_users()`
- `test_super_admin_can_create_admin_user()`
- `test_super_admin_can_suspend_admin_user()`
- `test_suspend_action_writes_audit_log()`
- `test_email_must_be_unique_on_create()`
- `test_password_confirmation_is_required()`

### Phase 3.3 — SecurityPolicyResource ✅

`app/Filament/SuperAdmin/Resources/SecurityPolicyResource.php`

Form: `TextInput(qr_expiry_seconds, min:10, max:300)`,
`TextInput(risk_auto_reject, min:50, max:100)`,
`TextInput(risk_pending_review, min:20, max:79)`,
`TextInput(late_threshold_mins)`, `TextInput(geofence_radius_m)`,
`Toggle(device_binding_required)`, `TextInput(clock_skew_seconds)`, `Toggle(is_active)`.
After save: `Cache::forget('security_policy.active')` + `AuditLog::record()`.

**Tests:** `tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php`
- `test_super_admin_can_edit_security_policy()`
- `test_qr_expiry_seconds_must_be_between_10_and_300()`
- `test_risk_auto_reject_must_be_between_50_and_100()`
- `test_risk_pending_review_must_be_between_20_and_79()`
- `test_save_clears_security_policy_cache()`
- `test_save_writes_audit_log()`

### Phase 3.4 — SystemSettingsPage ✅

`app/Filament/SuperAdmin/Pages/SystemSettingsPage.php`

Custom page. Individual `TextInput` / `Toggle` / `Select` per known system setting key.
On save calls `SystemSetting::set($key, $value)` for each field.
Loads current values from `SystemSetting::get($key)` on mount.

**Tests:** `tests/Feature/SuperAdmin/SystemSettingsPageTest.php`
- `test_super_admin_can_view_system_settings_page()`
- `test_system_settings_are_pre_populated_from_database()`
- `test_super_admin_can_save_system_settings()`
- `test_faculty_can_review_flags_toggle_persists()`

### Phase 3.5 — DataRetentionPolicyResource ✅

`app/Filament/SuperAdmin/Resources/DataRetentionPolicyResource.php`

Table: `entity_type`, `retention_days`, `is_active` badge, `last_run_at`.
Form: `TextInput(entity_type)`, `TextInput(retention_days, min:1)`, `Toggle(is_active)`.

**Tests:** `tests/Feature/SuperAdmin/DataRetentionPolicyResourceTest.php`
- `test_super_admin_can_list_retention_policies()`
- `test_super_admin_can_edit_retention_policy()`
- `test_retention_days_must_be_positive_integer()`

### Phase 3.6 — AdminRoleAssignmentResource ✅

`app/Filament/SuperAdmin/Resources/AdminRoleAssignmentResource.php`

Table: `user → name`, `role` badge, `department → name`, `assigned_at`, `revoked_at`.
Form: `Select(user_id, searchable)`, `Select(role: admin|faculty)`,
`Select(department_id)`, `DateTimePicker(assigned_at)`.
Actions: `EditAction`, `RevokeAction` (sets `revoked_at = now()` + `AuditLog::record()`).

**Tests:** `tests/Feature/SuperAdmin/AdminRoleAssignmentResourceTest.php`
- `test_super_admin_can_list_role_assignments()`
- `test_super_admin_can_create_role_assignment()`
- `test_super_admin_can_revoke_role_assignment()`
- `test_revoke_writes_audit_log()`

### Phase 3.7 — AuditLogResource (Read-Only) ✅

`app/Filament/SuperAdmin/Resources/AuditLogResource.php`

Table: `actor → name`, `actor_role`, `action`, `entity_type`, `entity_id`, `ip_address`, `created_at`.
Filters: `SelectFilter(action)`, `SelectFilter(actor_role)`, `DateRangeFilter(created_at)`.
Actions: `ViewAction` (modal with `old_values` / `new_values` JSON) — no create, no edit, no delete.
Header: `ExportAction` (CSV only).
`getEloquentQuery()`: no scope — super admin sees all.
Pagination default: 25.

**Tests:** `tests/Feature/SuperAdmin/AuditLogResourceTest.php`
- `test_super_admin_can_list_audit_logs()`
- `test_audit_log_has_no_create_action()`
- `test_audit_log_has_no_edit_action()`
- `test_audit_log_has_no_delete_action()`
- `test_super_admin_can_view_old_and_new_values_in_modal()`
- `test_super_admin_can_export_audit_log_csv()`
- `test_audit_log_filters_by_action_type()`
- `test_audit_log_filters_by_date_range()`

### Phase 3.8 — Super Admin Dashboard Widgets ✅

`app/Filament/SuperAdmin/Widgets/`

**`SuperAdminStatsOverviewWidget`** — `StatsOverviewWidget`, polling 30s.
Stats: total users (violet), active sessions (emerald), open proxy flags (amber), departments (sky).
Each stat wrapped in `Cache::remember(..., 60)`.

**`AttendanceTrendChartWidget`** — `ChartWidget` (bar), last 7 days daily attendance rate.

**`SystemHealthWidget`** — custom widget. Reads `SecurityPolicy::active()`, shows Redis
and queue worker connectivity, last retention run timestamp.

**`RecentAuditFeedWidget`** — custom widget. Latest 10 `AuditLog` rows, polling 20s.

**Tests:** `tests/Feature/SuperAdmin/DashboardWidgetTest.php`
- `test_stats_overview_widget_renders_for_super_admin()`
- `test_stats_overview_shows_correct_user_count()`
- `test_stats_overview_shows_correct_active_session_count()`
- `test_stats_overview_shows_correct_pending_proxy_flag_count()`
- `test_system_health_widget_renders()`
- `test_recent_audit_feed_widget_renders()`

---

## Phase 4: Admin Panel

### Phase 4.1 — StudentResource ✅

`app/Filament/Admin/Resources/StudentResource.php`

Table: `roll_no`, `user → name`, `department → name`, `batch_year`, `section`, `status` badge.
Form: `TextInput(roll_no)`, `Select(user_id, searchable, lazy)`, `Select(department_id)`,
`TextInput(batch_year)`, `TextInput(section)`, `Select(status)`.
Actions: `EditAction`, `ViewAttendanceAction` (redirect to AttendanceRecordResource filtered by student),
`ViewEnrollmentsAction`.
Filters: `SelectFilter(department_id)`, `SelectFilter(status)`, `SelectFilter(batch_year)`.
BulkActions: `ExportCsvBulkAction`, `EnrollInCourseBulkAction` (modal: course + group selector).
`getEloquentQuery()`: scoped to `auth()->user()->adminProfile->department_id`.
Pagination default: 25.

**Tests:** `tests/Feature/Admin/StudentResourceTest.php`
- `test_admin_can_list_students_in_own_department()`
- `test_admin_cannot_see_students_from_other_departments()`
- `test_admin_can_create_student()`
- `test_admin_can_edit_student()`
- `test_roll_no_must_be_unique()`
- `test_bulk_enroll_creates_enrollment_records()`

### Phase 4.2 — FacultyResource ✅

`app/Filament/Admin/Resources/FacultyResource.php`

Table: `employee_code`, `user → name`, `department → name`, `designation`, `status` badge, `sessions_count`.
Form: `Select(user_id)`, `TextInput(employee_code)`, `Select(department_id)`,
`TextInput(designation)`, `Select(status)`.
Actions: `EditAction`, `ViewSessionsAction`, `ViewTimetableAction`.
`getEloquentQuery()`: department-scoped.

**Tests:** `tests/Feature/Admin/FacultyResourceTest.php`
- `test_admin_can_list_faculty_in_own_department()`
- `test_admin_cannot_see_faculty_from_other_departments()`
- `test_admin_can_create_faculty()`
- `test_employee_code_must_be_unique()`

### Phase 4.3 — CourseResource & ClassGroupResource ✅

`app/Filament/Admin/Resources/CourseResource.php`
`app/Filament/Admin/Resources/ClassGroupResource.php`

CourseResource table: `code`, `name`, `department → name`, `semester`, `credits`,
`min_attendance_pct`, `enrollments_count`.
CourseResource form: all fields + `TextInput(min_attendance_pct, hint:'75 = 75%')`.
Actions: `EditAction`, `DeleteAction`, `ManageEnrollmentsAction`.

ClassGroupResource table: `name`, `course → code`.
ClassGroupResource form: `TextInput(name)`, `Select(course_id)`.

**Tests:** `tests/Feature/Admin/CourseResourceTest.php`
- `test_admin_can_list_courses()`
- `test_admin_can_create_course()`
- `test_course_code_must_be_unique()`
- `test_min_attendance_pct_must_be_between_0_and_100()`
- `test_soft_deleted_course_is_not_visible()`

### Phase 4.4 — RoomResource ✅

`app/Filament/Admin/Resources/RoomResource.php`

Table: `name`, `building`, `capacity`, `geofence_radius_m`,
`beacon_id` badge (configured / none), `is_active`.
Form: `TextInput(name)`, `TextInput(building)`, `TextInput(capacity)`,
`TextInput(latitude, numeric)`, `TextInput(longitude, numeric)`,
`TextInput(geofence_radius_m)`, `TextInput(beacon_id)`, `TextInput(wifi_ssid)`,
`Toggle(is_active)`.

**Tests:** `tests/Feature/Admin/RoomResourceTest.php`
- `test_admin_can_list_rooms()`
- `test_admin_can_create_room()`
- `test_admin_can_toggle_room_active_status()`
- `test_latitude_and_longitude_are_optional()`

### Phase 4.5 — TimetableResource ✅

`app/Filament/Admin/Resources/TimetableResource.php`

Table: `course → code`, `classGroup → name`, `faculty → user → name`, `room → name`,
`day_of_week` (formatted), `start_time`, `end_time`, `effective_from`.
Form: `Select(course_id)`, `Select(class_group_id)`, `Select(faculty_id)`,
`Select(room_id)`, `Select(day_of_week)`, `TimePicker(start_time)`, `TimePicker(end_time)`,
`DatePicker(effective_from)`, `DatePicker(effective_until)`.

**Tests:** `tests/Feature/Admin/TimetableResourceTest.php`
- `test_admin_can_list_timetables()`
- `test_admin_can_create_timetable_entry()`
- `test_start_time_must_be_before_end_time()`
- `test_effective_from_is_required()`

### Phase 4.6 — EnrollmentResource ✅

`app/Filament/Admin/Resources/EnrollmentResource.php`

Table: `student → roll_no`, `student → user → name`, `course → code`,
`classGroup → name`, `enrolled_at`, `status` badge.
Form: `Select(student_id, searchable, lazy)`, `Select(course_id)`,
`Select(class_group_id)`, `DatePicker(enrolled_at)`, `Select(status)`.
BulkActions: `DropBulkAction` (sets `status=dropped`), `MarkCompletedBulkAction`.

**Tests:** `tests/Feature/Admin/EnrollmentResourceTest.php`
- `test_admin_can_list_enrollments()`
- `test_admin_can_create_enrollment()`
- `test_student_cannot_be_enrolled_in_same_course_twice()`
- `test_bulk_drop_sets_status_to_dropped()`
- `test_bulk_mark_completed_sets_status_to_completed()`

### Phase 4.7 — ProxyFlagResource (Admin) ✅

`app/Filament/Admin/Resources/ProxyFlagResource.php`

Table: `attendanceRecord → student → user → name`, `attendanceRecord → session → course → code`,
`severity` badge, `reason_code`, `risk_score` (coloured: red ≥ 80, amber ≥ 50),
`review_status` badge, `created_at`. Default sort: `severity desc, created_at desc`.
Actions: `ApproveAction` (optional notes → `review_status=approved` + `AuditLog::record()`),
`RejectAction` (required notes → `review_status=rejected` + `AuditLog::record()`),
`ViewEvidenceAction` (modal with GPS, device info, risk breakdown from `evidence_json`).
BulkActions: `BulkApproveAction` (optional note), `BulkRejectAction` (required reason).
Filters: `SelectFilter(severity)`, `SelectFilter(review_status)`,
`DateRangeFilter(created_at)`, `SelectFilter(course via relationship)`.
Navigation badge: pending count.

**Tests:** `tests/Feature/Admin/ProxyFlagResourceTest.php`
- `test_admin_can_list_proxy_flags()`
- `test_admin_can_approve_proxy_flag_with_optional_note()`
- `test_admin_can_reject_proxy_flag_with_required_note()`
- `test_reject_without_notes_fails_validation()`
- `test_approve_action_writes_audit_log()`
- `test_reject_action_writes_audit_log()`
- `test_bulk_approve_updates_all_selected_flags()`
- `test_bulk_reject_requires_reason()`
- `test_navigation_badge_shows_pending_count()`

### Phase 4.8 — AttendanceRecordResource (Admin) ✅

`app/Filament/Admin/Resources/AttendanceRecordResource.php`

Table: `student → roll_no`, `student → user → name`, `session → course → code`,
`status` badge, `marked_at`, `risk_score` (coloured), `override_by → name`.
Actions: `OverrideAction` (modal: `Select(status)`, `Textarea(override_reason, min:20 chars)` →
`AuditLog::record()` + `override_by = auth()->id()`), `ViewEvidenceAction`.
Filters: `SelectFilter(status)`, `SelectFilter(course)`, `DateRangeFilter(marked_at)`,
`Filter(high_risk → risk_score >= 50)`.
`getEloquentQuery()`: department-scoped via session → course → department.

**Tests:** `tests/Feature/Admin/AttendanceRecordResourceTest.php`
- `test_admin_can_list_attendance_records()`
- `test_admin_cannot_see_records_from_other_departments()`
- `test_override_action_updates_status()`
- `test_override_reason_must_be_at_least_20_characters()`
- `test_override_action_writes_audit_log_with_old_and_new_values()`
- `test_override_sets_override_by_to_current_user()`
- `test_high_risk_filter_returns_records_with_risk_score_gte_50()`

### Phase 4.9 — AttendanceSessionResource (Admin, Read-Only) ✅

`app/Filament/Admin/Resources/AttendanceSessionResource.php`

Table: `course → code`, `classGroup → name`, `faculty → user → name`, `room → name`,
`status` badge, `started_at`, `closed_at`, `total_present / total_enrolled`.
No create or edit actions — read/review only.
`getEloquentQuery()`: scoped to department via course.

**Tests:** `tests/Feature/Admin/AttendanceSessionResourceTest.php`
- `test_admin_can_list_sessions_for_own_department()`
- `test_admin_cannot_see_sessions_from_other_departments()`
- `test_admin_cannot_create_sessions()`

### Phase 4.10 — ReportPage & DefaulterListPage ✅

`app/Filament/Admin/Pages/ReportPage.php`
`app/Filament/Admin/Pages/DefaulterListPage.php`

**ReportPage**: form with `Select(type)`, `Select(department_id)`, `Select(course_id)`,
`DatePicker(from)`, `DatePicker(to)`, `Select(format: pdf|csv|xlsx)`.
Submit dispatches `GenerateAttendanceReport` job. Shows download link on completion.

**DefaulterListPage**: table of students below `min_attendance_pct`.
Columns: student name, course, attended/total, attendance %, minimum %.
`ExportAction` → XLSX. `NotifyAction` → dispatches `SendAbsenceNotifications`.
Result cached 5 minutes.

**Tests:** `tests/Feature/Admin/ReportPageTest.php`
- `test_admin_can_view_report_page()`
- `test_report_form_dispatches_generate_report_job()`
- `test_report_form_requires_date_range()`

`tests/Feature/Admin/DefaulterListPageTest.php`
- `test_admin_can_view_defaulter_list()`
- `test_defaulter_list_only_shows_students_below_minimum_attendance()`
- `test_notify_action_dispatches_absence_notifications_job()`

### Phase 4.11 — Admin Dashboard Widgets ✅

`app/Filament/Admin/Widgets/`

**`AdminStatsOverviewWidget`**: students (dept), active sessions, pending proxy flags (red),
defaulters (amber). All stats wrapped in `Cache::remember(..., 60)`.

**`ActiveSessionsTableWidget`**: `TableWidget`, today's sessions, polling 15s.
Columns: course name, faculty, room, present/enrolled, status badge.

**`CourseAttendanceBarsWidget`**: horizontal bar chart per course. Courses below
`min_attendance_pct` highlighted red.

**`ProxyFlagAlertWidget`**: top 5 pending flags by severity. Approve/Reject inline.
Polling 10s.

**Tests:** `tests/Feature/Admin/DashboardWidgetTest.php`
- `test_stats_overview_widget_renders_for_admin()`
- `test_stats_are_scoped_to_admin_department()`
- `test_active_sessions_widget_renders()`
- `test_proxy_flag_alert_widget_renders()`

---

## Phase 5: Faculty Panel

### Phase 5.1 — AttendanceSessionResource (Faculty) ✅

`app/Filament/Faculty/Resources/AttendanceSessionResource.php`

Table: `course → code`, `classGroup → name`, `room → name`, `status` badge,
`started_at`, `closed_at`, `total_present / total_enrolled`, computed `duration`.
Actions: `StartAction` (pending only → `status=active` + `AuditLog::record()` → redirect to QrDisplayPage),
`ViewQrAction` (active only → redirect to QrDisplayPage),
`CloseAction` (modal with optional `close_reason` → dispatches `FinalizeAttendanceSession`),
`ExportAction` (modal: `Select(format)` → dispatches `GenerateAttendanceReport`),
`ReopenAction` (within grace window → `status=active`).
`getEloquentQuery()`: `where('faculty_id', auth()->user()->faculty->id)`.

**Tests:** `tests/Feature/Faculty/AttendanceSessionResourceTest.php`
- `test_faculty_can_list_own_sessions()`
- `test_faculty_cannot_see_other_faculty_sessions()`
- `test_start_action_sets_status_to_active()`
- `test_start_action_redirects_to_qr_display_page()`
- `test_close_action_dispatches_finalize_job()`
- `test_reopen_action_is_only_available_within_grace_window()`

### Phase 5.2 — QrDisplayPage ✅

`app/Filament/Faculty/Pages/QrDisplayPage.php`

Full-width Livewire page for classroom projection.

**Livewire properties:** `$qrString`, `$expiresIn`, `$sessionStats`, `$isActive`.
**`mount()`**: loads active session, calls `QRChallengeService::generateForSession()`.
**`refreshQr()`**: regenerates QR, resets `$expiresIn = qr_expiry_seconds`.
**`refreshStats()`**: re-queries session counters.
**`getListeners()`**: `['echo-private:session.{uuid},AttendanceMarked' => 'refreshStats']`.
**Polling**: `$poll = '30s'` on `refreshQr()`.
**Layout**: left 60% QR + countdown, right 40% live counters + recent feed + flag alerts.
**Header actions**: `CloseSessionAction`, `ExportSummaryAction`, `ForceRefreshQrAction`, `PauseSessionAction`.

**Tests:** `tests/Feature/Faculty/QrDisplayPageTest.php`
- `test_faculty_can_access_qr_display_page_for_active_session()`
- `test_qr_display_page_redirects_when_no_active_session()`
- `test_qr_string_is_populated_on_mount()`
- `test_refresh_qr_generates_new_qr_string()`
- `test_close_session_action_sets_status_to_closed()`
- `test_pause_session_action_sets_status_to_paused()`
- `test_force_refresh_generates_new_qr_immediately()`

### Phase 5.3 — TimetablePage ✅

`app/Filament/Faculty/Pages/TimetablePage.php`

Read-only calendar view of current week's timetable for `auth()->user()->faculty`.
Each slot: course code, class group, room, time.
`StartSessionAction` per slot → creates + starts session → redirects to QrDisplayPage.
Guard: no start if active session already exists for this slot today.

**Tests:** `tests/Feature/Faculty/TimetablePageTest.php`
- `test_faculty_can_view_own_timetable()`
- `test_timetable_shows_correct_week_slots()`
- `test_start_session_from_timetable_creates_session()`
- `test_start_session_fails_if_active_session_already_exists_for_slot()`

### Phase 5.4 — ProxyFlagResource (Faculty) ✅

`app/Filament/Faculty/Resources/ProxyFlagResource.php`

Table: student name, course, `severity` badge, `reason_code`, `risk_score`, `review_status`.
Actions: `ViewEvidenceAction`. `AllowAction` / `DenyAction` only if
`SystemSetting::get('faculty_can_review_flags') === 'true'`.
`getEloquentQuery()`: flags for own sessions only.

**Tests:** `tests/Feature/Faculty/ProxyFlagResourceTest.php`
- `test_faculty_can_list_flags_for_own_sessions()`
- `test_faculty_cannot_see_flags_from_other_sessions()`
- `test_allow_deny_actions_visible_when_policy_permits()`
- `test_allow_deny_actions_hidden_when_policy_disallows()`

### Phase 5.5 — AttendanceRecordResource (Faculty, Read-Only) ✅

`app/Filament/Faculty/Resources/AttendanceRecordResource.php`

Table: `student → roll_no`, `student → user → name`, `status` badge, `marked_at`, `risk_score`.
No override action. Filters: `SelectFilter(status)`, `SelectFilter(session)`.
`getEloquentQuery()`: own sessions only.

**Tests:** `tests/Feature/Faculty/AttendanceRecordResourceTest.php`
- `test_faculty_can_list_records_for_own_sessions()`
- `test_faculty_cannot_see_records_from_other_sessions()`
- `test_faculty_has_no_override_action()`

### Phase 5.6 — SessionExportResource ✅

`app/Filament/Faculty/Resources/SessionExportResource.php`

Table: `session → course → code`, `format` badge, `status` badge, `created_at`, `expires_at`.
Actions: `DownloadAction` (generates signed URL if `status=ready`, else shows "Processing…"),
`DeleteAction`.

**Tests:** `tests/Feature/Faculty/SessionExportResourceTest.php`
- `test_faculty_can_list_session_exports()`
- `test_download_action_provides_signed_url_when_ready()`
- `test_download_action_shows_processing_message_when_pending()`
- `test_faculty_can_delete_export()`

### Phase 5.7 — Faculty Dashboard Widgets ✅

`app/Filament/Faculty/Widgets/`

**`LiveSessionBannerWidget`**: polling 5s. If active session: course, room, elapsed time,
present/late/pending/absent counters, Close Session button, link to QrDisplayPage.
If no active session: today's timetable slots with Start Session button per slot.

**`FacultyStatsOverviewWidget`**: today's sessions count, 7-day avg attendance,
open proxy flags (amber), my courses count.

**`RecentScanFeedWidget`**: last 10 `AttendanceRecord` rows for active session.
Columns: student name, status badge, risk score dot, `marked_at`. Polling 3s.
Pending-review rows highlighted amber.

**`FlaggedScanAlertWidget`**: proxy flags on active session.
Per-flag: student name, risk score, reason code, Allow/Deny buttons. Shown only when active.

**Tests:** `tests/Feature/Faculty/DashboardWidgetTest.php`
- `test_live_session_banner_shows_active_session_for_faculty()`
- `test_live_session_banner_shows_timetable_when_no_active_session()`
- `test_recent_scan_feed_shows_last_10_records()`
- `test_flagged_scan_alert_is_hidden_when_no_active_session()`

---

## Phase 6: Background Jobs

### Phase 6.1 — GenerateAttendanceReport Job ✅

`app/Jobs/GenerateAttendanceReport.php`

Accepts: `type` (department|course|faculty|student|date_range), filter IDs, date range, `format` (pdf|csv|xlsx).
Generates the report file using `barryvdh/laravel-dompdf` (PDF) or `maatwebsite/excel` (CSV/XLSX).
Stores file to `storage/app/reports/` (private visibility).
Creates / updates `SessionExport` record with `status=ready` and `expires_at = now()+24h`.
Sends Filament notification to requesting user on completion.
Implements `ShouldQueue`. `$tries = 3`, `backoff = [1, 5, 10]`. Implements `failed()`.

**Tests:** `tests/Feature/Jobs/GenerateAttendanceReportTest.php`
- `test_job_creates_session_export_record_on_completion()`
- `test_job_sets_status_to_ready_with_file_path()`
- `test_job_sets_status_to_failed_on_exception()`
- `test_pdf_format_generates_pdf_file()`
- `test_xlsx_format_generates_xlsx_file()`

### Phase 6.2 — FinalizeAttendanceSession Job ⬜

`app/Jobs/FinalizeAttendanceSession.php`

Sets `status=closed` on the session. Computes final `total_present`, `total_late`,
`total_absent` from `attendance_records`. Marks enrolled students with no record as `absent`.
Writes `AuditLog::record('session.closed', ...)`.
Implements `ShouldQueue`, `$tries = 3`.

**Tests:** `tests/Feature/Jobs/FinalizeAttendanceSessionTest.php`
- `test_job_sets_session_status_to_closed()`
- `test_job_marks_enrolled_students_with_no_record_as_absent()`
- `test_job_updates_session_totals_correctly()`
- `test_job_writes_audit_log()`

### Phase 6.3 — SendAbsenceNotifications Job ⬜

`app/Jobs/SendAbsenceNotifications.php`

Accepts array of student IDs + course ID.
Sends a queued mail notification to each student's `user → email`.
Implements `ShouldQueue`. `$tries = 3`, `backoff = [1, 5, 10]`. Implements `failed()`.

**Tests:** `tests/Feature/Jobs/SendAbsenceNotificationsTest.php`
- `test_job_sends_email_to_each_student()`
- `test_job_includes_course_name_in_email()`
- `test_job_handles_missing_student_gracefully()`

---

## Phase 7: Student REST API

### Phase 7.1 — API Authentication & Device Registration ⬜

Routes prefix: `/api/v1/student/`. Sanctum token auth.

`POST /api/v1/student/auth/register` — create user (role=student) + student record + Sanctum token.
`POST /api/v1/student/auth/login` — return Sanctum token.
`POST /api/v1/student/devices` — register device fingerprint, enforce `max_devices_per_student`.
`GET  /api/v1/student/devices` — list own registered devices.
`DELETE /api/v1/student/devices/{id}` — remove a device.

Rate limiting: `throttle:60,1` on auth routes, `throttle:30,1` on device routes.

**Tests:** `tests/Feature/Api/StudentAuthTest.php`
- `test_student_can_register()`
- `test_student_can_login_and_receive_token()`
- `test_student_can_register_a_device()`
- `test_student_cannot_exceed_max_devices_limit()`
- `test_student_can_remove_own_device()`
- `test_registration_requires_unique_email()`
- `test_login_fails_with_wrong_password()`

### Phase 7.2 — QR Scan Endpoint ⬜

`POST /api/v1/student/attendance/scan`

Request: `{qr_payload: string, device_fingerprint: string, latitude: float, longitude: float}`.

Pipeline (inside DB transaction):
1. Decode + validate QR payload via `QRChallengeService::validateScan()`
2. Verify device fingerprint matches a registered device for the student
3. If `device_binding_required`: reject if device not primary
4. If geofence enabled: compute distance from room coordinates; reject if outside
5. Compute `risk_score` from: device mismatch weight, GPS distance weight, clock skew weight
6. Determine `status`: present / late (past `late_threshold_mins`) / pending_review (risk ≥ 50)
7. Auto-reject (skip record creation) if risk ≥ `risk_auto_reject`
8. Create `AttendanceRecord`
9. If `status=pending_review`: create `ProxyFlag` with computed severity and `evidence_json`
10. `broadcast(new AttendanceMarked(...))`
11. Return `{status, message, attendance_record_id}`

Rate limiting: `throttle:10,1` per student.

**Tests:** `tests/Feature/Api/QrScanTest.php`
- `test_valid_scan_creates_attendance_record_with_status_present()`
- `test_scan_after_late_threshold_creates_record_with_status_late()`
- `test_expired_qr_payload_is_rejected()`
- `test_tampered_qr_payload_is_rejected()`
- `test_scan_with_unregistered_device_is_rejected()`
- `test_scan_outside_geofence_raises_risk_score()`
- `test_high_risk_scan_creates_proxy_flag()`
- `test_auto_reject_threshold_skips_record_creation()`
- `test_duplicate_scan_for_same_session_is_rejected()`
- `test_scan_broadcasts_attendance_marked_event()`

### Phase 7.3 — Attendance History Endpoint ⬜

`GET /api/v1/student/attendance` — paginated list of own records with session + course info.
`GET /api/v1/student/attendance/summary` — per-course: attended, total, percentage, minimum.

**Tests:** `tests/Feature/Api/AttendanceHistoryTest.php`
- `test_student_can_retrieve_own_attendance_records()`
- `test_student_cannot_retrieve_another_students_records()`
- `test_attendance_summary_returns_correct_percentages()`
- `test_attendance_summary_flags_courses_below_minimum()`

---

## Summary

| Phase | Tasks | Status |
|---|---|---|
| 1 — DB Structure | 1.1–1.9 | ✅✅✅✅✅✅✅✅✅ |
| 2 — Core Services | 2.1–2.4 | ✅✅✅✅ |
| 3 — Super Admin Panel | 3.1–3.8 | ✅✅✅✅✅✅✅✅ |
| 4 — Admin Panel | 4.1–4.11 | ✅✅✅✅✅✅✅✅✅✅✅ |
| 5 — Faculty Panel | 5.1–5.7 | ✅✅✅✅✅✅✅ |
| 6 — Background Jobs | 6.1–6.3 | ✅⬜⬜ |
| 7 — Student REST API | 7.1–7.3 | ⬜⬜⬜ |

**Total tasks:** 45 (2 complete, 1 partial, 42 pending)
