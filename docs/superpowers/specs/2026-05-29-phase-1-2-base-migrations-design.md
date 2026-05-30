# Phase 1.2 — Base Database Migrations (Fresh Implementation)

## Context

The existing domain migrations exist but contain schema bugs. This spec describes replacing them with clean, correct migrations that match the Phase 1.2 requirements in `docs/project-phases.md`.

## Approach

Option A — Replace: delete all existing `2026_01_01_*` domain migrations, revert the vanilla users migration, and create 9 clean domain migrations from scratch. Run `migrate:fresh` to rebuild the database.

## Files Changed

### Removed

| File | Reason |
|---|---|
| `database/migrations/2026_01_01_000002_create_departments_table.php` | Replaced |
| `database/migrations/2026_01_01_000003_create_students_table.php` | Replaced |
| `database/migrations/2026_01_01_000004_create_faculty_table.php` | Replaced |
| `database/migrations/2026_01_01_000005_create_super_admin_tables.php` | Replaced |
| `database/migrations/2026_01_01_000006_create_admin_tables.php` | Replaced |
| `database/migrations/2026_01_01_000007_create_device_registrations_table.php` | Replaced |
| `database/migrations/2026_01_01_000008_create_faculty_tables.php` | Replaced |
| `database/migrations/2026_01_01_000009_create_proxy_audit_tables.php` | Replaced |
| `database/migrations/2026_05_27_204600_add_policy_name_to_security_policies.php` | Folded into base |

### Reverted

- `database/migrations/0001_01_01_000000_create_users_table.php` — revert local changes; restore `Schema::dropIfExists('users')` in `down()`

### Kept Untouched

- `0001_01_01_000001_create_cache_table.php`
- `0001_01_01_000002_create_jobs_table.php`
- `2026_05_27_202202_create_personal_access_tokens_table.php`
- `2026_05_28_065954_create_media_table.php`
- `2026_05_28_065956_amend_enum_columns_to_string.php` (Phase 1.3)
- `2026_05_29_122004_add_proxy_signal_weights_to_security_policies.php` (Phase 4+)

## New Migration Structure (9 files)

All new files use the prefix `2026_01_01_0000XX_` to keep them ordered before the Phase 1.3 amendment migration.

### 1. `2026_01_01_000002_extend_users_table.php`

Adds to the existing `users` table:
- `role` enum(`super_admin`, `admin`, `faculty`, `student`) default `student`, after `email`
- `status` enum(`active`, `suspended`, `inactive`) default `active`, after `role`
- `last_login_at` timestamp nullable, after `remember_token`
- index on `role`, index on `status`

`down()` drops indexes then columns.

### 2. `2026_01_01_000003_create_departments_table.php`

Columns: `id`, `name` string unique, `code` string(10) unique, `head_faculty_id` unsignedBigInteger nullable (FK added in migration 3), `is_active` boolean default true, `timestamps`.

Index: `is_active`.

`down()`: `dropIfExists('departments')`.

### 3. `2026_01_01_000004_create_faculty_table.php`

Creates `faculty`:
- `id`, `user_id` FK→users cascade, `department_id` FK→departments restrict
- `employee_code` string unique
- `designation` string nullable
- `status` enum(`active`, `on_leave`, `inactive`) default `active`
- `timestamps`

Indexes: `department_id`, `status`.

Then resolves the circular FK: `Schema::table('departments')` adds foreign key on `head_faculty_id` → `faculty.id` nullOnDelete.

`down()`: drops `head_faculty_id` FK from departments, then `dropIfExists('faculty')`.

### 4. `2026_01_01_000005_create_students_table.php`

Creates `students`:
- `id`, `user_id` FK→users cascade, `department_id` FK→departments restrict
- `roll_no` string unique
- `batch_year` string(4)
- `section` string nullable
- `status` enum(`active`, `suspended`, `graduated`, `dropped`) default `active`
- `softDeletes`, `timestamps`

Indexes: `department_id`, `status`, `batch_year`, composite `[department_id, status]`.

`down()`: `dropIfExists('students')`.

### 5. `2026_01_01_000006_create_security_system_tables.php`

Creates four tables in order:

**`security_policies`**: `id`, `policy_name` string nullable, `qr_expiry_seconds` smallInt default 30, `risk_auto_reject` tinyInt default 80, `risk_pending_review` tinyInt default 50, `late_threshold_mins` smallInt default 15, `geofence_radius_m` smallInt default 50, `device_binding_required` boolean default true, `clock_skew_seconds` smallInt default 60, `is_active` boolean default false, `created_by` FK→users nullOnDelete nullable, `timestamps`. Index: `is_active`.

**`system_settings`**: `id`, `key` string unique, `value` text nullable, `timestamps`.

**`data_retention_policies`**: `id`, `entity_type` string, `retention_days` smallInt unsigned, `is_active` boolean default true, `last_run_at` timestamp nullable, `timestamps`. Indexes: `entity_type`, `is_active`.

**`admin_role_assignments`**: `id`, `user_id` FK→users cascade, `assigned_by` FK→users restrict, `role` enum(`admin`, `faculty`), `department_id` FK→departments nullOnDelete nullable, `assigned_at` timestamp, `revoked_at` timestamp nullable, `timestamps`. Indexes: `user_id`, `role`, `department_id`.

`down()`: drops tables in reverse order.

### 6. `2026_01_01_000007_create_academic_tables.php`

**`courses`**: `id`, `department_id` FK→departments restrict, `code` string unique, `name` string, `semester` string, `credits` tinyInt unsigned, `min_attendance_pct` tinyInt unsigned default 75, `softDeletes`, `timestamps`. Index: `department_id`.

**`class_groups`**: `id`, `course_id` FK→courses cascade, `name` string, `timestamps`. Index: `course_id`.

**`rooms`**: `id`, `name` string, `building` string nullable, `capacity` smallInt unsigned nullable, `latitude` decimal(10,7) nullable, `longitude` decimal(10,7) nullable, `geofence_radius_m` smallInt unsigned nullable, `beacon_id` string nullable, `wifi_ssid` string nullable, `is_active` boolean default true, `timestamps`. Index: `is_active`.

`down()`: drops rooms, class_groups, courses.

### 7. `2026_01_01_000008_create_timetables_enrollments_table.php`

**`timetables`**: `id`, `course_id` FK→courses cascade, `class_group_id` FK→class_groups cascade, `faculty_id` FK→faculty restrict, `room_id` FK→rooms nullOnDelete nullable, `day_of_week` enum(`monday`…`sunday`), `start_time` time, `end_time` time, `effective_from` date, `effective_until` date nullable, `timestamps`. Indexes: `faculty_id`, `course_id`, composite `[day_of_week, start_time]`.

**`enrollments`**: `id`, `student_id` FK→students cascade, `course_id` FK→courses restrict, `class_group_id` FK→class_groups restrict, `enrolled_at` datetime, `status` enum(`active`, `dropped`, `completed`) default `active`, `timestamps`. Unique: `[student_id, course_id, class_group_id]`. Indexes: `course_id`, `class_group_id`, `status`.

`down()`: drops enrollments, timetables.

### 8. `2026_01_01_000009_create_device_registrations_attendance_tables.php`

**`device_registrations`**: `id`, `user_id` FK→users cascade, `device_fingerprint` string, `device_name` string nullable, `platform` string nullable, `app_version` string nullable, `is_trusted` boolean default false, `last_seen_at` timestamp nullable, `timestamps`. Unique: `[user_id, device_fingerprint]`. Index: `user_id`.

**`attendance_sessions`**: `id`, `uuid` uuid unique, `faculty_id` FK→faculty restrict, `course_id` FK→courses restrict, `class_group_id` FK→class_groups restrict, `room_id` FK→rooms nullOnDelete nullable, `timetable_id` FK→timetables nullOnDelete nullable, `status` enum(`pending`, `active`, `paused`, `closed`) default `pending`, `started_at` timestamp nullable, `closed_at` timestamp nullable, `close_reason` text nullable, `total_enrolled` smallInt unsigned default 0, `total_present` smallInt unsigned default 0, `total_late` smallInt unsigned default 0, `total_absent` smallInt unsigned default 0, `timestamps`. Indexes: `faculty_id`, `course_id`, `status`, `started_at`, composite `[faculty_id, status]`.

**`attendance_records`**: `id`, `session_id` FK→attendance_sessions cascade, `student_id` FK→students restrict, `enrollment_id` FK→enrollments nullOnDelete nullable, `status` enum(`present`, `late`, `absent`, `pending_review`, `rejected`) default `pending_review`, `marked_at` timestamp nullable, `risk_score` tinyInt unsigned default 0, `latitude` decimal(10,7) nullable, `longitude` decimal(10,7) nullable, `device_id` FK→device_registrations nullOnDelete nullable, `evidence_json` json nullable, `override_by` FK→users nullOnDelete nullable, `override_reason` text nullable, `overridden_at` timestamp nullable, `timestamps`. Unique: `[session_id, student_id]`. Indexes: `session_id`, `student_id`, `status`, `risk_score`.

**`session_exports`**: `id`, `session_id` FK→attendance_sessions cascade, `requested_by` FK→users restrict, `format` enum(`pdf`, `csv`, `xlsx`), `status` enum(`pending`, `processing`, `ready`, `failed`) default `pending`, `file_path` string nullable, `expires_at` timestamp nullable, `timestamps`. Indexes: `session_id`, `status`.

`down()`: drops session_exports, attendance_records, attendance_sessions, device_registrations.

### 9. `2026_01_01_000010_create_proxy_audit_tables.php`

**`proxy_flags`**: `id`, `attendance_record_id` FK→attendance_records cascade, `severity` enum(`low`, `medium`, `high`, `critical`), `reason_code` string, `risk_score` tinyInt unsigned, `evidence_json` json nullable, `review_status` enum(`pending`, `approved`, `rejected`) default `pending`, `reviewer_id` FK→users nullOnDelete nullable, `reviewer_notes` text nullable, `reviewed_at` timestamp nullable, `timestamps`. Indexes: `attendance_record_id`, `severity`, `review_status`, `risk_score`, composite `[severity, review_status]`.

**`audit_logs`**: `id`, `actor_id` unsignedBigInteger nullable (no FK constraint — actor may be deleted), `actor_role` string nullable, `action` string, `entity_type` string nullable, `entity_id` unsignedBigInteger nullable, `old_values` json nullable, `new_values` json nullable, `ip_address` string(45) nullable, `user_agent` string nullable, `timestamps`. Indexes: `actor_id`, `action`, composite `[entity_type, entity_id]`, `created_at`.

`down()`: drops audit_logs, proxy_flags.

## Schema Corrections vs Existing

| Table | Bug in existing | Corrected value |
|---|---|---|
| `students` | `status` enum includes `withdrawn` | Replace with `dropped` |
| `device_registrations` | Wrong columns (`device_type`, `is_primary`, `registered_at`) | Replace with `device_name`, `app_version`, `is_trusted`, `last_seen_at` |
| `attendance_records` | FK column named `attendance_session_id` | Rename to `session_id` |
| `enrollments` | `enrolled_at` is `date` | Change to `datetime` |
| `security_policies` | Missing `policy_name` | Add `policy_name` string nullable |
| `faculty_tables` | Contains extra `qr_challenges` table | Omit — not in Phase 1.2 spec |
| `users` | `down()` drops columns only | Restore `Schema::dropIfExists('users')` |

## Post-Implementation

After creating the migrations, run `php artisan migrate:fresh --seed` to verify the schema builds cleanly from scratch.
