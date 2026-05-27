# Secure Dynamic QR Attendance System
## Project Description — Filament v4 Web Portal Edition

**Stack:** Laravel 12 (PHP 8.3) · Filament v4 · Flutter 3 · MySQL 8.0 · Redis 7 · Laravel Reverb  
**Version:** 2.0 · **Status:** Active Development  
**TRS Reference:** `TRS_QR_Attendance_System_v1.0.docx`  
**Portal Framework:** [Filament v4](https://filamentphp.com)

---

## Overview

A production-grade automated classroom attendance platform. Three separate Filament v4 **panels** serve the Super Admin, Admin, and Faculty roles — each with its own URL path, navigation, middleware, colour theme, and resource scope. The Flutter mobile app handles the student-facing scan experience and connects to the same Laravel API backend.

Filament replaces hand-written Blade controllers and Livewire components for all three web-portal roles. Every dashboard widget, resource table, form, action, and notification is declared using Filament's panel API.

---

## Why Filament v4 for This Project

| Concern | Filament v4 Solution |
|---|---|
| Three distinct admin roles with different nav | Three separate `Panel` registrations with `->id()`, `->path()`, `->middleware()` |
| Real-time live attendance counters | `StatsOverviewWidget` + Livewire polling or `->pollingInterval()` |
| Proxy flag review queue with batch actions | `Table::make()` with `BulkAction`, `Action`, custom `ActionGroup` |
| Dynamic QR display with 30-second countdown | Custom `Widget` with Livewire component and polling |
| Manual attendance overrides with reason field | `Action::make()` with a `form()` modal and mandatory `Textarea` |
| Audit logs (append-only, read-only) | Read-only `Resource` with no `CreateAction` or `EditAction` |
| Configurable security policy | `SettingsPage` using `spatie/laravel-settings` or custom form |
| Complex filters (date range, department, course) | `Filter`, `SelectFilter`, `DateRangeFilter` on every table |
| Role-scoped data (admin sees own department only) | Panel-level `->authGuard()` + `Resource::getEloquentQuery()` scope |
| XLSX / PDF report exports | `ExportAction` with `XlsxExporter` and `PdfExporter` |

---

## Panel Architecture

Three Filament panels registered in `app/Providers/Filament/`:

```
SuperAdminPanelProvider   → /super-admin  (role: super_admin)
AdminPanelProvider        → /admin        (role: admin)
FacultyPanelProvider      → /faculty      (role: faculty)
```

Each panel uses:
- `->authMiddleware(['auth', 'role:<panel_role>'])` — blocks wrong roles at panel level
- `->colors(['primary' => Color::Violet])` — distinct colour per panel
- Its own navigation groups, resources, pages, and widgets
- Shared models and services from `app/Models/` and `app/Services/`

```php
// app/Providers/Filament/SuperAdminPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('super-admin')
        ->path('super-admin')
        ->login()
        ->colors(['primary' => Color::Violet])
        ->authMiddleware(['auth', 'verified', EnsureRole::class . ':super_admin'])
        ->discoverResources(in: app_path('Filament/SuperAdmin/Resources'), for: 'App\\Filament\\SuperAdmin\\Resources')
        ->discoverPages(in: app_path('Filament/SuperAdmin/Pages'), for: 'App\\Filament\\SuperAdmin\\Pages')
        ->discoverWidgets(in: app_path('Filament/SuperAdmin/Widgets'), for: 'App\\Filament\\SuperAdmin\\Widgets')
        ->navigationGroups(['Overview', 'Administration', 'Security', 'Audit']);
}
```

---

## Super Admin Panel — `/super-admin`

**Identity:** Platform owner. Controls system-wide configuration, security policy, QR key management, department setup, admin accounts, and all audit logs.

**Theme:** Violet (`Color::Violet`)

### Navigation Structure

```
Overview
  └── Dashboard (StatsOverviewWidget + ActivityFeedWidget + SystemHealthWidget)

Administration
  ├── Departments        (DepartmentResource)
  └── Admin Accounts     (AdminUserResource)

Security
  ├── Security Policies  (SecurityPolicyResource / SettingsPage)
  ├── System Settings    (SystemSettingsPage)
  └── Data Retention     (DataRetentionPolicyResource)

Audit
  ├── Audit Logs         (AuditLogResource — read-only)
  └── Role Assignments   (AdminRoleAssignmentResource)
```

### Dashboard Widgets

**`SuperAdminStatsOverviewWidget`** — `StatsOverviewWidget`
```
Stat: Total Users        → users count, colour: Violet, icon: users
Stat: Active Sessions    → attendance_sessions where status=active, colour: Emerald, icon: play-circle
Stat: Open Proxy Flags   → proxy_flags where review_status=pending, colour: Amber, icon: flag
Stat: Departments        → departments count, colour: Sky, icon: building-office
```
Polling interval: `->pollingInterval('30s')`

**`AttendanceTrendChartWidget`** — `ChartWidget` (Bar)
- Last 7 days daily attendance rate (present + late / enrolled)
- Drill-down: click bar → filter by date in `AttendanceRecordResource`

**`SystemHealthWidget`** — custom `Widget`
- Reads `SecurityPolicy::active()` and displays key settings as a checklist
- Shows Redis connectivity status, queue worker status, last retention run

**`RecentAuditFeedWidget`** — custom `Widget`
- Latest 10 `AuditLog` rows, live-polling every 20s
- Links to full `AuditLogResource`

### Resources

**`DepartmentResource`**
```php
Table columns:  name, code, headFaculty->user->name, students_count, faculty_count, is_active badge
Form fields:    TextInput(name), TextInput(code), Select(head_faculty_id), Toggle(is_active)
Actions:        Edit, Delete (soft), ViewStudents (redirect to AdminPanel scoped)
Filters:        SelectFilter(is_active)
```

**`AdminUserResource`**
```php
Table columns:  name, email, role badge, status badge, last_login_at, department
Form fields:    TextInput(name), TextInput(email), TextInput(password confirmed),
                Select(role: admin), Select(department_id), Toggle(status)
Actions:        Edit, Suspend (sets status=suspended + AuditLog), Revoke role
BulkActions:    Suspend selected, Export CSV
Header actions: CreateAction
```

**`SecurityPolicyResource`** — wraps `security_policies` table
```php
Form fields:    TextInput(qr_expiry_seconds, numeric, min:10, max:300)
                TextInput(risk_auto_reject, numeric, min:50, max:100)
                TextInput(risk_pending_review, numeric, min:20, max:79)
                TextInput(late_threshold_mins, numeric)
                TextInput(geofence_radius_m, numeric)
                Toggle(device_binding_required)
                TextInput(clock_skew_seconds, numeric)
                Toggle(is_active)
After save:     Cache::forget('security_policy.active')   → clears policy cache
                AuditLog::record(...)
```

**`SystemSettingsPage`** — custom `Page` using `Filament\Pages\Page`
```php
// key-value editor for system_settings table
// Uses repeater or individual TextInput/Toggle/Select per known key
// Calls SystemSetting::set() on save
```

**`AuditLogResource`** — read-only
```php
Table columns:  actor->name, actor_role, action, entity_type, entity_id, ip_address, created_at
Filters:        SelectFilter(action), SelectFilter(actor_role), DateRangeFilter(created_at)
Actions:        ViewAction (shows old_values / new_values JSON in modal) — NO edit, NO delete
Header actions: ExportAction (CSV only)
getEloquentQuery(): no scope — super admin sees all
```

---

## Admin Panel — `/admin`

**Identity:** Institutional manager. CRUD on academic entities, enrollment management, proxy flag review queue, manual overrides, and department-scoped reports.

**Theme:** Emerald (`Color::Emerald`)

### Navigation Structure

```
Overview
  ├── Dashboard          (StatsOverviewWidget + SessionsListWidget + ProxyAlertWidget)
  └── Proxy Review       (ProxyFlagResource — urgent, badge shows pending count)

Academic Management
  ├── Students           (StudentResource)
  ├── Faculty            (FacultyResource)
  ├── Courses            (CourseResource)
  ├── Class Groups       (ClassGroupResource)
  ├── Rooms              (RoomResource)
  └── Timetables         (TimetableResource)

Attendance
  ├── Sessions           (AttendanceSessionResource — read/review, no create)
  ├── Records            (AttendanceRecordResource — with override action)
  └── Enrollments        (EnrollmentResource)

Reports
  ├── Attendance Reports (ReportPage)
  ├── Defaulters         (DefaulterListPage)
  └── Audit Logs         (AuditLogResource — department scope only)
```

**Navigation badge** on Proxy Review: `->badge(fn() => ProxyFlag::pending()->count())->badgeColor('danger')`

### Dashboard Widgets

**`AdminStatsOverviewWidget`** — `StatsOverviewWidget`
```
Stat: Students (dept)    → students scoped to admin's department
Stat: Active Sessions    → sessions for dept courses
Stat: Proxy Flags        → pending flags for dept sessions, colour: Red
Stat: Defaulters         → students below min_attendance_pct, colour: Amber
```

**`ActiveSessionsTableWidget`** — `TableWidget`
- Live table of today's sessions (status: active, closed)
- Columns: course name, faculty, room, present/enrolled, status badge
- Polling: `->pollingInterval('15s')`

**`CourseAttendanceBarsWidget`** — custom `Widget`
- Horizontal bar chart per course showing attendance %
- Courses below `min_attendance_pct` highlighted red

**`ProxyFlagAlertWidget`** — custom `Widget`
- Top 5 most severe pending proxy flags
- Quick-action: Approve / Reject inline buttons
- Links to full `ProxyFlagResource`

### Resources

**`StudentResource`**
```php
Table columns:  roll_no, user->name, department->name, batch_year, section, status badge
Form fields:    TextInput(roll_no), Select(user_id — searchable), Select(department_id),
                TextInput(batch_year), TextInput(section), Select(status)
Actions:        Edit, ViewAttendance (filter AttendanceRecordResource by student),
                ViewEnrollments
Filters:        SelectFilter(department_id), SelectFilter(status), SelectFilter(batch_year)
BulkActions:    Export CSV, Enroll in Course (opens modal with course+group selector)
getEloquentQuery(): →whereHas('department', fn($q) => $q->where('id', auth()->user()->admin->department_id))
```

**`FacultyResource`**
```php
Table columns:  employee_code, user->name, department->name, designation, status badge, sessions_count
Form fields:    Select(user_id), TextInput(employee_code), Select(department_id),
                TextInput(designation), Select(status)
Actions:        Edit, ViewSessions, ViewTimetable
getEloquentQuery(): department-scoped
```

**`CourseResource`**
```php
Table columns:  code, name, department->name, semester, credits, min_attendance_pct, enrollments_count
Form fields:    TextInput(code), TextInput(name), Select(department_id), TextInput(semester),
                TextInput(credits), TextInput(min_attendance_pct, hint:'75 = 75%')
Actions:        Edit, Delete, ManageEnrollments
```

**`RoomResource`**
```php
Table columns:  name, building, capacity, geofence_radius_m, beacon_id (badge: configured/none), is_active
Form fields:    TextInput(name), TextInput(building), TextInput(capacity),
                TextInput(latitude, numeric), TextInput(longitude, numeric),
                TextInput(geofence_radius_m), TextInput(beacon_id), TextInput(wifi_ssid),
                Toggle(is_active)
```

**`TimetableResource`**
```php
Table columns:  course->code, classGroup->name, faculty->user->name, room->name,
                day_of_week (formatted), start_time, end_time, effective_from
Form fields:    Select(course_id), Select(class_group_id), Select(faculty_id),
                Select(room_id), Select(day_of_week: [Mon..Sun]),
                TimePicker(start_time), TimePicker(end_time),
                DatePicker(effective_from), DatePicker(effective_until)
```

**`EnrollmentResource`**
```php
Table columns:  student->roll_no, student->user->name, course->code, classGroup->name,
                enrolled_at, status badge
Form fields:    Select(student_id — searchable), Select(course_id), Select(class_group_id),
                DatePicker(enrolled_at), Select(status)
BulkActions:    Drop selected, Mark completed
```

**`ProxyFlagResource`** — the admin's primary review queue
```php
Table columns:  attendanceRecord->student->user->name, attendanceRecord->session->course->code,
                severity badge, reason_code, risk_score (coloured: red≥80 amber≥50),
                review_status badge, created_at
Actions:
  ApproveAction → modal: optional TextArea(reviewer_notes) → set review_status=approved + AuditLog
  RejectAction  → modal: required TextArea(reviewer_notes) → set review_status=rejected + AuditLog
  ViewEvidenceAction → modal showing evidence_json formatted, GPS coords, device info
BulkActions:
  BulkApproveAction → approve all selected with optional bulk note
  BulkRejectAction  → reject all selected with mandatory reason
Filters:        SelectFilter(severity), SelectFilter(review_status), DateRangeFilter(created_at),
                SelectFilter(course via relationship)
Default sort:   severity desc, created_at desc
```

**`AttendanceRecordResource`**
```php
Table columns:  student->roll_no, student->user->name, session->course->code,
                status badge, marked_at, risk_score (coloured), override_by->name
Actions:
  OverrideAction → modal with:
    Select(status: present|late|absent|pending_review)
    Textarea(override_reason — required, min 20 chars)
    → Writes AuditLog with old_values + new_values
    → Sets override_by = auth()->id()
  ViewEvidenceAction → shows evidence_json + GPS in modal (admin only)
Filters:        SelectFilter(status), SelectFilter(course), DateRangeFilter(marked_at),
                Filter(high_risk → risk_score >= 50)
```

**`ReportPage`** — custom `Page`
```php
// Form: Select(type: department|course|faculty|student|date_range)
//       Select(department_id), Select(course_id), DatePicker(from), DatePicker(to)
//       Select(format: pdf|csv|xlsx)
// Submit → dispatch(new GenerateAttendanceReport(...))
// Show download link when job completes (polling or notification)
```

**`DefaulterListPage`** — custom `Page`
```php
// Table: student name, course, attended/total, attendance_pct, min required
// Computed from attendance_records + enrollments
// ExportAction → XLSX
// NotifyAction → dispatch SendAbsenceNotifications job for selected students
```

---

## Faculty Panel — `/faculty`

**Identity:** Classroom instructor. Starts/closes sessions, displays the live QR, monitors real-time attendance counts, reviews flagged scans, and exports session summaries.

**Theme:** Orange (`Color::Orange`)

### Navigation Structure

```
My Sessions
  ├── Dashboard          (LiveSessionWidget + QrDisplayWidget + CounterWidget)
  └── All Sessions       (AttendanceSessionResource — own sessions only)

My Classes
  ├── My Timetable       (TimetablePage — read-only view of own schedule)
  ├── My Courses         (CourseResource — read-only, own assigned courses)
  └── My Students        (StudentResource — read-only, enrolled in own courses)

Records
  ├── Attendance Records (AttendanceRecordResource — own sessions only)
  ├── Flagged Scans      (ProxyFlagResource — own sessions, review if policy allows)
  └── Session Exports    (SessionExportResource)
```

### Dashboard Widgets

**`LiveSessionBannerWidget`** — custom `Widget` (most prominent)
```php
// Checks for an active session owned by auth()->user()->faculty
// If ACTIVE: shows course name, room, start time, elapsed time
//            present/late/pending/absent live counters
//            "Close Session" button → calls POST /api/v1/faculty/sessions/{id}/close
//            links to QrDisplayPage
// If NO ACTIVE SESSION: shows today's timetable slots with "Start Session" button per slot
// Polling: ->pollingInterval('5s')
```

**`FacultyStatsOverviewWidget`** — `StatsOverviewWidget`
```
Stat: Today's Sessions   → count of today's sessions for this faculty
Stat: Avg Attendance     → rolling 7-day average for own courses
Stat: Flagged Scans      → open proxy flags on own sessions, colour: Amber
Stat: My Courses         → count of courses in current semester
```

**`RecentScanFeedWidget`** — custom `Widget`
- Last 10 `AttendanceRecord` rows for the active session
- Columns: student name, status badge, risk score dot, marked_at
- Polling: `->pollingInterval('3s')`
- Highlight pending_review rows in amber

**`FlaggedScanAlertWidget`** — custom `Widget`
- Shows proxy flags on currently active session
- Per-flag: student name, risk score, reason code, Allow/Deny quick-action buttons
- Only shown when session is active

### Pages

**`QrDisplayPage`** — custom `Page` (the most important faculty page)
```php
// Full-width QR display for classroom projection
// Livewire properties:
//   $qrString       — current base64 QR string
//   $expiresIn      — seconds remaining (counts down from 30)
//   $sessionStats   — { present, late, pending, rejected, absent, enrolled }
//   $isActive       — bool

// mount(): load active session, generate first QR via QRChallengeService
// getListeners(): ['echo:session.{uuid},AttendanceMarked' => 'refreshStats']

// Template layout:
//   Left (60%): QR code image rendered from $qrString
//               Countdown ring (30s → 0, then auto-refresh)
//               Session metadata: course, room, slot time, faculty
//   Right (40%): Live attendance counters (present, late, pending, absent)
//                Recent scan feed (last 5)
//                Active proxy flag alerts

// Actions (in page header):
//   CloseSessionAction  → confirmation modal → POST /api/v1/faculty/sessions/{id}/close
//   ExportSummaryAction → dispatch GenerateAttendanceReport → download link
//   ForceRefreshQrAction → regenerate QR immediately
//   PauseSessionAction  → sets status=paused (suspends QR rotation)

// QR rotation: Livewire $poll set to 30s → calls refreshQr()
// refreshQr(): calls QRChallengeService::generateForSession(), updates $qrString, resets $expiresIn
// Countdown: JavaScript setInterval every 1s decrementing $expiresIn display
```

**`TimetablePage`** — custom `Page` (read-only calendar view)
```php
// Shows current week's timetable for auth()->user()->faculty
// Each slot shows: course code, class group, room, time
// "Start Session" button on each slot → creates + starts session → redirects to QrDisplayPage
// Checks: no active session already exists for this slot today
```

### Resources

**`AttendanceSessionResource`** — faculty scope
```php
Table columns:  course->code, classGroup->name, room->name, status badge,
                started_at, closed_at, total_present/total_enrolled, duration
Actions:
  StartAction  → only on pending sessions → sets status=active + AuditLog → redirect to QrDisplayPage
  ViewQrAction → redirects to QrDisplayPage (only for active sessions)
  CloseAction  → modal with optional close_reason → dispatches FinalizeAttendanceSession
  ExportAction → modal: Select(format: pdf|csv|xlsx) → dispatches GenerateAttendanceReport
  ReopenAction → only if within configurable grace window → sets status=active
getEloquentQuery(): →where('faculty_id', auth()->user()->faculty->id)
```

**`ProxyFlagResource`** — faculty scope (view + limited review)
```php
// Only shows flags for own sessions
// Review actions only available if SystemSetting::get('faculty_can_review_flags') = true
Table columns:  student name, course, severity badge, reason_code, risk_score, review_status
Actions:        ViewEvidenceAction (modal with GPS, device, risk breakdown)
                AllowAction / DenyAction (if policy permits)
getEloquentQuery(): →whereHas('attendanceRecord.session', fn($q) => $q->where('faculty_id', auth()->user()->faculty->id))
```

**`AttendanceRecordResource`** — faculty scope
```php
// Read-only — no override action (admin-only)
Table columns:  student->roll_no, student->user->name, status badge, marked_at, risk_score
Filters:        SelectFilter(status), SelectFilter(session)
getEloquentQuery(): →whereHas('session', fn($q) => $q->where('faculty_id', auth()->user()->faculty->id))
```

**`SessionExportResource`**
```php
Table columns:  session->course->code, format badge, status badge, created_at, expires_at
Actions:        DownloadAction → generates signed URL if status=ready, else "Processing..."
                DeleteAction → removes file and record
```

---

## Shared Filament Patterns

### Role Guard Middleware

```php
// app/Http/Middleware/EnsureRole.php
// Used in every panel's ->authMiddleware([EnsureRole::class . ':super_admin'])
// Reads auth()->user()->role, aborts 403 if mismatch
```

### Audit Logging in Actions

Every `Action`, `BulkAction`, and form save that modifies data must call:
```php
AuditLog::record(
    action: 'proxy_flag.approved',
    entity: $proxyFlag,
    oldValues: $proxyFlag->getOriginal(),
    newValues: $proxyFlag->getChanges(),
);
```
Encapsulate this in a trait `LogsToAudit` mixed into Resource classes.

### Department Scoping

Admin panel resources scope all queries to the admin's department:
```php
// In every Admin Resource:
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->where('department_id', auth()->user()->adminProfile->department_id);
}
```
Super admin resources have no scope — they see everything.

### Notification Pattern

Use Filament's built-in notification system for all user feedback:
```php
Notification::make()
    ->title('Attendance overridden')
    ->body("Status changed to {$newStatus}")
    ->success()
    ->send();
```
For failed actions:
```php
Notification::make()->title('Action failed')->danger()->send();
```

### Empty State Handling

Every table that may be empty must declare:
```php
->emptyStateHeading('No flagged scans')
->emptyStateDescription('All scans for this session have been cleared.')
->emptyStateIcon('heroicon-o-check-circle')
```

---

## Real-Time Architecture with Filament

### Live QR + Attendance Counter Flow

```
Student scans QR
      │
      ▼
POST /api/v1/student/attendance/scan
      │
      ▼ (inside DB transaction)
AttendanceRecord created
      │
      ▼
broadcast(new AttendanceMarked($record, $session))
      │  Laravel Reverb → channel: session.{uuid}
      ▼
QrDisplayPage (Livewire) receives echo event
      │  getListeners(): ['echo:session.{uuid},AttendanceMarked' => 'refreshStats']
      ▼
$this->sessionStats refreshed → counters update in browser
```

### Livewire Polling Strategy

| Widget | Polling | Reason |
|---|---|---|
| `LiveSessionBannerWidget` | 5s | Needs to detect new session starts |
| `RecentScanFeedWidget` | 3s | Near-real-time scan feed |
| `QrDisplayPage` QR refresh | 30s | Matches QR expiry window |
| `ActiveSessionsTableWidget` | 15s | Admin overview — less critical |
| `SuperAdminStatsOverviewWidget` | 30s | Summary stats |
| `ProxyFlagAlertWidget` | 10s | Time-sensitive review alerts |

---

## File Structure — Filament Panels

```
app/
├── Filament/
│   ├── SuperAdmin/
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   └── SystemSettingsPage.php
│   │   ├── Resources/
│   │   │   ├── DepartmentResource.php
│   │   │   │   └── Pages/  (ListDepartments, CreateDepartment, EditDepartment)
│   │   │   ├── AdminUserResource.php
│   │   │   ├── SecurityPolicyResource.php
│   │   │   ├── DataRetentionPolicyResource.php
│   │   │   ├── AdminRoleAssignmentResource.php
│   │   │   └── AuditLogResource.php
│   │   └── Widgets/
│   │       ├── SuperAdminStatsOverviewWidget.php
│   │       ├── AttendanceTrendChartWidget.php
│   │       ├── SystemHealthWidget.php
│   │       └── RecentAuditFeedWidget.php
│   │
│   ├── Admin/
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── ReportPage.php
│   │   │   └── DefaulterListPage.php
│   │   ├── Resources/
│   │   │   ├── StudentResource.php
│   │   │   ├── FacultyResource.php
│   │   │   ├── CourseResource.php
│   │   │   ├── ClassGroupResource.php
│   │   │   ├── RoomResource.php
│   │   │   ├── TimetableResource.php
│   │   │   ├── EnrollmentResource.php
│   │   │   ├── ProxyFlagResource.php
│   │   │   ├── AttendanceRecordResource.php
│   │   │   └── AttendanceSessionResource.php
│   │   └── Widgets/
│   │       ├── AdminStatsOverviewWidget.php
│   │       ├── ActiveSessionsTableWidget.php
│   │       ├── CourseAttendanceBarsWidget.php
│   │       └── ProxyFlagAlertWidget.php
│   │
│   └── Faculty/
│       ├── Pages/
│       │   ├── Dashboard.php
│       │   ├── QrDisplayPage.php         ← most complex page
│       │   └── TimetablePage.php
│       ├── Resources/
│       │   ├── AttendanceSessionResource.php
│       │   ├── AttendanceRecordResource.php
│       │   ├── ProxyFlagResource.php
│       │   └── SessionExportResource.php
│       └── Widgets/
│           ├── LiveSessionBannerWidget.php
│           ├── FacultyStatsOverviewWidget.php
│           ├── RecentScanFeedWidget.php
│           └── FlaggedScanAlertWidget.php
│
└── Providers/
    └── Filament/
        ├── SuperAdminPanelProvider.php
        ├── AdminPanelProvider.php
        └── FacultyPanelProvider.php
```

---

## Filament v4 Package Dependencies

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "laravel/sanctum": "^4.0",
        "filament/filament": "^4.0",
        "laravel/reverb": "^1.0",
        "spatie/laravel-permission": "^6.0",
        "barryvdh/laravel-dompdf": "^3.0",
        "maatwebsite/excel": "^3.1",
        "simplesoftwareio/simple-qrcode": "^4.2"
    },
    "require-dev": {
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0",
        "phpstan/phpstan": "^1.10"
    }
}
```

Install Filament v4 panels:
```bash
composer require filament/filament:"^4.0"
php artisan filament:install --panels
```

---

## Environment Variables

```env
# Core
APP_KEY=
APP_URL=https://your-domain.com

# Database
DB_HOST=127.0.0.1
DB_DATABASE=qr_attendance
DB_USERNAME=qr_user
DB_PASSWORD=

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=

# QR Security
QR_SECRET=                        # ≥32 random bytes — HMAC-SHA256 signing key

# Filament Panel Paths (optional override)
FILAMENT_SUPER_ADMIN_PATH=super-admin
FILAMENT_ADMIN_PATH=admin
FILAMENT_FACULTY_PATH=faculty

# Reverb WebSocket
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080

# Queue
QUEUE_CONNECTION=redis

# Mail (absence notifications)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PASSWORD=

# Storage
FILESYSTEM_DISK=local           # or s3
AWS_SECRET_ACCESS_KEY=          # if s3
```

---

## Performance Notes

- All `StatsOverviewWidget` stat values that require aggregation (counts, percentages) should be wrapped in `Cache::remember()` with a 60-second TTL — recomputing every poll is expensive
- `QrDisplayPage` must pull QR from Redis cache (`QRChallengeService`), not regenerate on every Livewire refresh
- `DefaulterListPage` query is computationally expensive — dispatch as a background job and cache the result for 5 minutes
- Filament table pagination defaults to 10; set `->defaultPaginationPageOption(25)` for admin tables
- Use `->lazy()` on `Select` fields that load large datasets (e.g., student or course selects with 1000+ rows)
