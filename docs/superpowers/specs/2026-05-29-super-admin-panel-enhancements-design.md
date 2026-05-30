# Super Admin Panel — Enhancements Design Spec

**Date:** 2026-05-29  
**Status:** Approved

---

## Context

The Super Admin Panel (`/super-admin`, Violet) was built in Phase 3. Its shell — 6 resources, 4 widgets, 1 custom page, and the panel provider — is fully committed and functional. This spec addresses the five specific gaps identified against the stated requirements:

1. Dashboard security posture checklist is incomplete (only 3 items)
2. Security Policies form lacks the 9 proxy signal weight fields
3. Admin Accounts table has Suspend but no Reinstate action
4. Departments renders as a standard table, not a grid of cards
5. Audit Logs is already complete — no changes needed

---

## Architecture

All changes are targeted file-level edits to existing components. No new providers, no new base classes, no new navigation entries. The panel auto-discovers everything from `app/Filament/SuperAdmin/`.

---

## Changes by Component

### 1. Proxy Signal Weights — Migration + Model + Form

**Why:** The proxy detection engine weights 9 independent signals when computing a scan's `risk_score`. These weights are policy-level configuration, so they belong on `security_policies` alongside the existing risk thresholds.

**Migration** (`database/migrations/YYYY_MM_DD_add_proxy_signal_weights_to_security_policies.php`):
Add 9 `unsignedTinyInteger` columns (0–100) each with `default(20)`:

| Column | Label |
|---|---|
| `w_gps` | GPS Location Weight |
| `w_device` | Device Fingerprint Weight |
| `w_clock_skew` | Clock Skew Weight |
| `w_wifi` | WiFi SSID Weight |
| `w_beacon` | Bluetooth Beacon Weight |
| `w_ip_cluster` | IP Cluster Weight |
| `w_speed` | Movement Speed Weight |
| `w_peer_scan` | Peer Scan Weight |
| `w_biometric` | Biometric Weight |

**Model** (`app/Models/SecurityPolicy.php`): Add all 9 columns to `$fillable`.

**Factory** (`database/factories/SecurityPolicyFactory.php`): Add each weight as `fake()->numberBetween(0, 100)`.

**Form** (`app/Filament/SuperAdmin/Resources/SecurityPolicies/Schemas/SecurityPolicyForm.php`):
- Wrap existing 9 fields in `Section::make('General Settings')`
- Add `Section::make('Proxy Signal Weights')` → `Grid::make(3)` → 9 `TextInput` fields, each `->numeric()->minValue(0)->maxValue(100)->required()`

### 2. Admin Accounts — Reinstate Action

**Why:** Suspended users must be reactivatable without a full edit form.

**File:** `app/Filament/SuperAdmin/Resources/AdminUsers/Tables/AdminUsersTable.php`

Add after the existing `suspend` action:
```
Action::make('reinstate')
    ->label('Reinstate')
    ->icon(Heroicon::OutlinedCheckCircle)
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn (User $record) => $record->status === UserStatus::Suspended)
    ->action(function (User $record) {
        $old = ['status' => $record->status->value];
        $record->update(['status' => UserStatus::Active]);
        AuditLog::record('user.reinstated', $record, $old, ['status' => UserStatus::Active->value]);
        Notification::make()->title('User reinstated')->success()->send();
    })
```

### 3. Departments — Card Grid

**Why:** A grid of cards gives a better overview of departments than a dense table.

**ListDepartments** (`app/Filament/SuperAdmin/Resources/Departments/Pages/ListDepartments.php`):
- Change `extends ListRecords` → `extends Page`
- Set `protected string $view = 'filament.super-admin.pages.list-departments'`
- Add `#[Computed] public function departments(): Collection` — queries `Department::withCount(['students', 'faculty'])->with('headFaculty.user')->orderBy('name')->get()`
- Add `public function deleteDepartment(int $id): void` — deletes record, notifies
- Header actions: `Action::make('create')->url(DepartmentResource::getUrl('create'))->icon(Heroicon::OutlinedPlus)`

**Blade view** (`resources/views/filament/super-admin/pages/list-departments.blade.php`):
- `<x-filament-panels::page>` wrapper
- Responsive 3-column grid (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4`)
- Each card (`<x-filament::section>`):
  - Header row: department name + active/inactive badge
  - Code line in muted text
  - DL with: Head of Faculty, Students count, Faculty count
  - Footer row: Edit link (→ `DepartmentResource::getUrl('edit', ...)`) + Delete button (`wire:click="deleteDepartment({{ $dept->id }})"` + `wire:confirm`)
- Empty state: simple centred message when no departments exist

**DepartmentResource** (`app/Filament/SuperAdmin/Resources/Departments/DepartmentResource.php`):
- No page-registration changes needed; `'index' => ListDepartments::route('/')` already points to the class being rewritten

### 4. Dashboard — Security Posture Checklist

**Why:** The existing `SystemHealthWidget` only checks Redis, active policy name, and last retention run. The dashboard requirement calls for a security posture checklist.

**SystemHealthWidget** (`app/Filament/SuperAdmin/Widgets/SystemHealthWidget.php`):  
Extend `getViewData()` to pass 4 additional booleans derived from the active policy:
- `deviceBindingEnabled` — `$policy?->device_binding_required === true`
- `autoRejectConfigured` — `$policy?->risk_auto_reject >= 50`
- `geofenceConfigured` — `$policy?->geofence_radius_m > 0`
- `qrExpiryShort` — `$policy?->qr_expiry_seconds <= 60`

**Blade view** (`resources/views/filament/super-admin/widgets/system-health-widget.blade.php`):  
Add a "Security Posture" section below the existing health grid. Render each item as a labelled row with a green check (`✓ Pass`) or red cross (`✗ Fail`) badge.

---

## Tests

All tests live under `tests/Feature/SuperAdmin/`.

### SecurityPolicyResourceTest.php — add 2 tests
- `proxy_signal_weights_can_be_saved` — fill all 9 weight fields, call save, assert DB
- `proxy_signal_weight_rejects_value_above_100` — fill `w_gps => 101`, assert form error

### AdminUserResourceTest.php — add 2 tests
- `super_admin_can_reinstate_suspended_user` — suspend user, call reinstate action, assert status Active
- `reinstate_action_writes_audit_log` — assert `user.reinstated` audit entry exists

### DepartmentResourceTest.php — rewrite 2 tests
- `super_admin_can_list_departments` — change from `assertCanSeeTableRecords` to `assertSee($dept->name)` (card grid renders department names in HTML)
- `super_admin_can_delete_department` — change from `callAction(TestAction::make('delete')->table(...))` to `call('deleteDepartment', $dept->id)` + `assertDatabaseMissing`

Create/Edit/validation tests are unchanged.

---

## Verification

1. Run `php artisan migrate` — confirm 9 new columns on `security_policies`
2. Visit `/super-admin/security-policies/{id}/edit` — confirm "Proxy Signal Weights" section renders with 9 fields
3. Visit `/super-admin/admin-users` — confirm Reinstate appears only on suspended rows; clicking reinstates and logs
4. Visit `/super-admin/departments` — confirm card grid renders, Edit navigates correctly, Delete removes with confirmation
5. Visit `/super-admin` (Dashboard) — confirm Security Posture checklist shows pass/fail for all 4 items
6. Run `php artisan test --compact --filter=SuperAdmin` — all tests pass
