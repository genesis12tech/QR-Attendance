# Phase 1.2 — Base Database Migrations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace existing domain migrations with clean, spec-correct versions that match the Phase 1.2 schema definition.

**Architecture:** Delete 8 existing domain migration files and 1 obsolete amendment, revert the vanilla users migration to its original state, then create 9 ordered domain migrations (`2026_01_01_000002` through `000010`). The Phase 1.3 amendment (`2026_05_28_065956_amend_enum_columns_to_string`) and the Phase 4+ proxy signal weights migration remain untouched — they run after and are fully compatible.

**Tech Stack:** Laravel 12 migrations, MySQL/InnoDB, PHP 8.4 (`/Users/thomas/.config/herd-lite/bin/php`)

---

## File Map

| Action | Path |
|---|---|
| Revert | `database/migrations/0001_01_01_000000_create_users_table.php` |
| Delete | `database/migrations/2026_01_01_000002_create_departments_table.php` |
| Delete | `database/migrations/2026_01_01_000003_create_students_table.php` |
| Delete | `database/migrations/2026_01_01_000004_create_faculty_table.php` |
| Delete | `database/migrations/2026_01_01_000005_create_super_admin_tables.php` |
| Delete | `database/migrations/2026_01_01_000006_create_admin_tables.php` |
| Delete | `database/migrations/2026_01_01_000007_create_device_registrations_table.php` |
| Delete | `database/migrations/2026_01_01_000008_create_faculty_tables.php` |
| Delete | `database/migrations/2026_01_01_000009_create_proxy_audit_tables.php` |
| Delete | `database/migrations/2026_05_27_204600_add_policy_name_to_security_policies.php` |
| Create | `database/migrations/2026_01_01_000002_extend_users_table.php` |
| Create | `database/migrations/2026_01_01_000003_create_departments_table.php` |
| Create | `database/migrations/2026_01_01_000004_create_faculty_table.php` |
| Create | `database/migrations/2026_01_01_000005_create_students_table.php` |
| Create | `database/migrations/2026_01_01_000006_create_security_system_tables.php` |
| Create | `database/migrations/2026_01_01_000007_create_academic_tables.php` |
| Create | `database/migrations/2026_01_01_000008_create_timetables_enrollments_table.php` |
| Create | `database/migrations/2026_01_01_000009_create_device_registrations_attendance_tables.php` |
| Create | `database/migrations/2026_01_01_000010_create_proxy_audit_tables.php` |

---

## Task 1: Clean Up Existing Migrations

**Files:** Delete 9 existing files, revert 1

- [ ] **Step 1: Delete the old domain migrations and the obsolete amendment**

```bash
rm database/migrations/2026_01_01_000002_create_departments_table.php
rm database/migrations/2026_01_01_000003_create_students_table.php
rm database/migrations/2026_01_01_000004_create_faculty_table.php
rm database/migrations/2026_01_01_000005_create_super_admin_tables.php
rm database/migrations/2026_01_01_000006_create_admin_tables.php
rm database/migrations/2026_01_01_000007_create_device_registrations_table.php
rm database/migrations/2026_01_01_000008_create_faculty_tables.php
rm database/migrations/2026_01_01_000009_create_proxy_audit_tables.php
rm database/migrations/2026_05_27_204600_add_policy_name_to_security_policies.php
```

- [ ] **Step 2: Revert the users migration to its vanilla Laravel state**

Replace the entire contents of `database/migrations/0001_01_01_000000_create_users_table.php` with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
```

- [ ] **Step 3: Verify the migrations directory has the right files**

```bash
ls database/migrations/
```

Expected output contains only: the 3 `0001_01_01_*` files, `2026_05_27_202202_create_personal_access_tokens_table.php`, `2026_05_28_065954_create_media_table.php`, `2026_05_28_065956_amend_enum_columns_to_string.php`, `2026_05_29_122004_add_proxy_signal_weights_to_security_policies.php`.

- [ ] **Step 4: Run pint on the reverted file**

```bash
vendor/bin/pint database/migrations/0001_01_01_000000_create_users_table.php --format agent
```

- [ ] **Step 5: Commit**

```bash
git add database/migrations/
git commit -m "chore: remove old domain migrations for Phase 1.2 rebuild"
```

---

## Task 2: Migration 1 — Extend Users Table

**File:** `database/migrations/2026_01_01_000002_extend_users_table.php`

- [ ] **Step 1: Create the file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin', 'faculty', 'student'])->default('student')->after('email');
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active')->after('role');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->index('role');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['role', 'status', 'last_login_at']);
        });
    }
};
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000002_extend_users_table.php --format agent
```

- [ ] **Step 3: Verify migration runs without error**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan migrate:fresh
```

Expected: no errors. If an error mentions a missing table (e.g., from Phase 1.3 amendment referencing a table not yet created), that's okay at this step — more migrations are coming.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_01_01_000002_extend_users_table.php
git commit -m "feat(migration): extend users table with role, status, last_login_at"
```

---

## Task 3: Migration 2 — Departments

**File:** `database/migrations/2026_01_01_000003_create_departments_table.php`

- [ ] **Step 1: Create the file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->unsignedBigInteger('head_faculty_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
```

Note: `head_faculty_id` has no FK constraint yet — the circular dependency (departments → faculty → departments) is resolved in Task 4.

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000003_create_departments_table.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_01_01_000003_create_departments_table.php
git commit -m "feat(migration): create departments table"
```

---

## Task 4: Migration 3 — Faculty (+ resolve departments FK)

**File:** `database/migrations/2026_01_01_000004_create_faculty_table.php`

- [ ] **Step 1: Create the file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('employee_code')->unique();
            $table->string('designation')->nullable();
            $table->enum('status', ['active', 'on_leave', 'inactive'])->default('active');
            $table->timestamps();
            $table->index('department_id');
            $table->index('status');
        });

        // Resolve circular FK: departments.head_faculty_id → faculty
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('head_faculty_id')
                ->references('id')->on('faculty')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['head_faculty_id']);
        });

        Schema::dropIfExists('faculty');
    }
};
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000004_create_faculty_table.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_01_01_000004_create_faculty_table.php
git commit -m "feat(migration): create faculty table and resolve departments circular FK"
```

---

## Task 5: Migration 4 — Students

**File:** `database/migrations/2026_01_01_000005_create_students_table.php`

- [ ] **Step 1: Create the file**

Fix: existing migration had `withdrawn` in status enum — correct value is `dropped`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('roll_no')->unique();
            $table->string('batch_year', 4);
            $table->string('section')->nullable();
            $table->enum('status', ['active', 'suspended', 'graduated', 'dropped'])->default('active');
            $table->softDeletes();
            $table->timestamps();
            $table->index('department_id');
            $table->index('status');
            $table->index('batch_year');
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000005_create_students_table.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_01_01_000005_create_students_table.php
git commit -m "feat(migration): create students table"
```

---

## Task 6: Migration 5 — Security & System Tables

**File:** `database/migrations/2026_01_01_000006_create_security_system_tables.php`

- [ ] **Step 1: Create the file**

Fix: existing migration was missing `policy_name` (now included here, removing the separate amendment migration).

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_name')->nullable();
            $table->unsignedSmallInteger('qr_expiry_seconds')->default(30);
            $table->unsignedTinyInteger('risk_auto_reject')->default(80);
            $table->unsignedTinyInteger('risk_pending_review')->default(50);
            $table->unsignedSmallInteger('late_threshold_mins')->default(15);
            $table->unsignedSmallInteger('geofence_radius_m')->default(50);
            $table->boolean('device_binding_required')->default(true);
            $table->unsignedSmallInteger('clock_skew_seconds')->default(60);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('is_active');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedSmallInteger('retention_days');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->index('entity_type');
            $table->index('is_active');
        });

        Schema::create('admin_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->enum('role', ['admin', 'faculty']);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('role');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_assignments');
        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('security_policies');
    }
};
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000006_create_security_system_tables.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_01_01_000006_create_security_system_tables.php
git commit -m "feat(migration): create security_policies, system_settings, data_retention_policies, admin_role_assignments"
```

---

## Task 7: Migration 6 — Academic Tables

**File:** `database/migrations/2026_01_01_000007_create_academic_tables.php`

- [ ] **Step 1: Create the file**

Fix: `min_attendance_pct` uses `decimal(5,2)` to match the model's `decimal:2` cast (e.g., stores `75.00`, not integer `75`).

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('semester');
            $table->unsignedTinyInteger('credits');
            $table->decimal('min_attendance_pct', 5, 2)->default(75.00);
            $table->softDeletes();
            $table->timestamps();
            $table->index('department_id');
        });

        Schema::create('class_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->index('course_id');
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('building')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('geofence_radius_m')->nullable();
            $table->string('beacon_id')->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('class_groups');
        Schema::dropIfExists('courses');
    }
};
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000007_create_academic_tables.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_01_01_000007_create_academic_tables.php
git commit -m "feat(migration): create courses, class_groups, rooms"
```

---

## Task 8: Migration 7 — Timetables & Enrollments

**File:** `database/migrations/2026_01_01_000008_create_timetables_enrollments_table.php`

- [ ] **Step 1: Create the file**

Fix: `enrolled_at` is `datetime()` not `date()` — matches the model's `datetime` cast.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('faculty_id')->constrained('faculty')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('start_time');
            $table->time('end_time');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->index('faculty_id');
            $table->index('course_id');
            $table->index(['day_of_week', 'start_time']);
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_group_id')->constrained()->restrictOnDelete();
            $table->datetime('enrolled_at');
            $table->enum('status', ['active', 'dropped', 'completed'])->default('active');
            $table->timestamps();
            $table->unique(['student_id', 'course_id', 'class_group_id'], 'uq_enrollments_student_course_group');
            $table->index('course_id');
            $table->index('class_group_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('timetables');
    }
};
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000008_create_timetables_enrollments_table.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_01_01_000008_create_timetables_enrollments_table.php
git commit -m "feat(migration): create timetables and enrollments"
```

---

## Task 9: Migration 8 — Device Registrations & Attendance Tables

**File:** `database/migrations/2026_01_01_000009_create_device_registrations_attendance_tables.php`

- [ ] **Step 1: Create the file**

Fixes:
- `device_registrations`: replaces `device_type`/`is_primary`/`registered_at` with `device_name`/`app_version`/`is_trusted`/`last_seen_at`
- `attendance_records`: FK column named `session_id` (was `attendance_session_id`)
- `qr_challenges` table removed (not in Phase 1.2 spec)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_fingerprint');
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('app_version')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_fingerprint']);
            $table->index('user_id');
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('faculty_id')->constrained('faculty')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('timetable_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'paused', 'closed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_reason')->nullable();
            $table->unsignedSmallInteger('total_enrolled')->default(0);
            $table->unsignedSmallInteger('total_present')->default(0);
            $table->unsignedSmallInteger('total_late')->default(0);
            $table->unsignedSmallInteger('total_absent')->default(0);
            $table->timestamps();
            $table->index('faculty_id');
            $table->index('course_id');
            $table->index('status');
            $table->index('started_at');
            $table->index(['faculty_id', 'status']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['present', 'late', 'absent', 'pending_review', 'rejected'])->default('pending_review');
            $table->timestamp('marked_at')->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('device_id')->nullable()->constrained('device_registrations')->nullOnDelete();
            $table->json('evidence_json')->nullable();
            $table->foreignId('override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'student_id'], 'uq_attendance_session_student');
            $table->index('session_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('risk_score');
        });

        Schema::create('session_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->enum('format', ['pdf', 'csv', 'xlsx']);
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('session_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_exports');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('device_registrations');
    }
};
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000009_create_device_registrations_attendance_tables.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_01_01_000009_create_device_registrations_attendance_tables.php
git commit -m "feat(migration): create device_registrations, attendance_sessions, attendance_records, session_exports"
```

---

## Task 10: Migration 9 — Proxy Flags & Audit Logs

**File:** `database/migrations/2026_01_01_000010_create_proxy_audit_tables.php`

- [ ] **Step 1: Create the file**

Note: `audit_logs.actor_id` has no FK constraint intentionally — actors can be deleted without cascading audit history.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('reason_code');
            $table->unsignedTinyInteger('risk_score');
            $table->json('evidence_json')->nullable();
            $table->enum('review_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index('attendance_record_id');
            $table->index('severity');
            $table->index('review_status');
            $table->index('risk_score');
            $table->index(['severity', 'review_status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index('actor_id');
            $table->index('action');
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('proxy_flags');
    }
};
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint database/migrations/2026_01_01_000010_create_proxy_audit_tables.php --format agent
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_01_01_000010_create_proxy_audit_tables.php
git commit -m "feat(migration): create proxy_flags and audit_logs"
```

---

## Task 11: Final Verification

- [ ] **Step 1: Run migrate:fresh to verify the full schema builds cleanly**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan migrate:fresh
```

Expected: all migrations run without error, ending with a line like:
```
INFO  Running migrations.
...
2026_05_29_122004_add_proxy_signal_weights_to_security_policies ..... XXms DONE
```

If any migration fails, check the error message — it will name the migration and the SQL error. Common issues: wrong column name in FK reference, dropped table before dependents.

- [ ] **Step 2: Verify all expected tables exist**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan tinker --execute '
$tables = [
    "users", "departments", "students", "faculty",
    "security_policies", "system_settings", "data_retention_policies", "admin_role_assignments",
    "courses", "class_groups", "rooms", "timetables", "enrollments",
    "device_registrations", "attendance_sessions", "attendance_records", "session_exports",
    "proxy_flags", "audit_logs",
];
foreach ($tables as $t) {
    echo $t . ": " . (Schema::hasTable($t) ? "OK" : "MISSING") . PHP_EOL;
}'
```

Expected: all 20 tables show `OK`.

- [ ] **Step 3: Spot-check the corrected columns**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan tinker --execute '
echo "students.status type: " . Schema::getColumnType("students", "status") . PHP_EOL;
echo "device_registrations has is_trusted: " . (Schema::hasColumn("device_registrations", "is_trusted") ? "YES" : "NO") . PHP_EOL;
echo "device_registrations has device_type: " . (Schema::hasColumn("device_registrations", "device_type") ? "YES (BUG)" : "NO (correct)") . PHP_EOL;
echo "attendance_records has session_id: " . (Schema::hasColumn("attendance_records", "session_id") ? "YES" : "NO") . PHP_EOL;
echo "attendance_records has attendance_session_id: " . (Schema::hasColumn("attendance_records", "attendance_session_id") ? "YES (BUG)" : "NO (correct)") . PHP_EOL;
echo "security_policies has policy_name: " . (Schema::hasColumn("security_policies", "policy_name") ? "YES" : "NO") . PHP_EOL;
echo "enrollments.enrolled_at type: " . Schema::getColumnType("enrollments", "enrolled_at") . PHP_EOL;
'
```

Expected:
```
students.status type: string   ← Phase 1.3 amendment already ran
device_registrations has is_trusted: YES
device_registrations has device_type: NO (correct)
attendance_records has session_id: YES
attendance_records has attendance_session_id: NO (correct)
security_policies has policy_name: YES
enrollments.enrolled_at type: datetime
```

- [ ] **Step 4: Run existing test suite to check for regressions**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
```

Some tests may fail if they reference the old `attendance_session_id` FK name or `device_type` / `is_primary` columns on `device_registrations`. Note any failures — they are follow-up fixes in the model/factory/test layer (out of scope for Phase 1.2 migrations, which is schema-only).

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "feat: Phase 1.2 base database migrations complete"
```
