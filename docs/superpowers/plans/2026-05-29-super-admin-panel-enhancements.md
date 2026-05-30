# Super Admin Panel Enhancements — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enhance the existing Super Admin panel with proxy signal weights on the Security Policy form, a Reinstate action on Admin Users, a Departments card grid, and an extended security posture checklist on the Dashboard.

**Architecture:** All changes are targeted edits to existing files — no new panel providers, no new navigation entries, no new resource registrations. Filament v4 auto-discovers everything under `app/Filament/SuperAdmin/`. The Departments list page is rewritten from `ListRecords` to a custom `Page` with a Blade card grid. Proxy signal weights are added via migration + model fill + factory + form section.

**Tech Stack:** PHP 8.4 · Laravel 12 · Filament v4 · Livewire 3 · Pest 3 · Heroicon enum · PHP binary: `/Users/thomas/.config/herd-lite/bin/php`

---

## File Map

| Action | Path | Purpose |
|---|---|---|
| Create | `database/migrations/2026_05_29_120000_add_proxy_signal_weights_to_security_policies.php` | Add 9 weight columns |
| Modify | `app/Models/SecurityPolicy.php` | Add 9 fields to `$fillable`; extend `beforeSave` field list |
| Modify | `database/factories/SecurityPolicyFactory.php` | Add 9 weight fields |
| Modify | `app/Filament/SuperAdmin/Resources/SecurityPolicies/Schemas/SecurityPolicyForm.php` | Add Proxy Signal Weights section |
| Modify | `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/EditSecurityPolicy.php` | Include weight fields in `beforeSave` / `afterSave` snapshots |
| Modify | `tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php` | Add 2 weight tests |
| Modify | `app/Filament/SuperAdmin/Resources/AdminUsers/Tables/AdminUsersTable.php` | Add `reinstate` action |
| Modify | `tests/Feature/SuperAdmin/AdminUserResourceTest.php` | Add 2 reinstate tests |
| Rewrite | `app/Filament/SuperAdmin/Resources/Departments/Pages/ListDepartments.php` | Custom `Page` with computed departments + delete method |
| Create | `resources/views/filament/super-admin/pages/list-departments.blade.php` | Card grid Blade view |
| Modify | `tests/Feature/SuperAdmin/DepartmentResourceTest.php` | Rewrite list + delete tests for card page |
| Modify | `app/Filament/SuperAdmin/Widgets/SystemHealthWidget.php` | Add 4 security posture booleans to `getViewData()` |
| Modify | `resources/views/filament/super-admin/widgets/system-health-widget.blade.php` | Add security posture checklist section |
| Modify | `tests/Feature/SuperAdmin/DashboardWidgetTest.php` | Add system health checklist test |

---

## Task 1 — Proxy Signal Weights: Migration + Model + Factory

**Files:**
- Create: `database/migrations/2026_05_29_120000_add_proxy_signal_weights_to_security_policies.php`
- Modify: `app/Models/SecurityPolicy.php`
- Modify: `database/factories/SecurityPolicyFactory.php`

- [ ] **Step 1.1 — Create the migration**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:migration add_proxy_signal_weights_to_security_policies --no-interaction
```

Replace the generated file body with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('security_policies', function (Blueprint $table) {
            $table->unsignedTinyInteger('w_gps')->default(20)->after('clock_skew_seconds');
            $table->unsignedTinyInteger('w_device')->default(20)->after('w_gps');
            $table->unsignedTinyInteger('w_clock_skew')->default(20)->after('w_device');
            $table->unsignedTinyInteger('w_wifi')->default(20)->after('w_clock_skew');
            $table->unsignedTinyInteger('w_beacon')->default(20)->after('w_wifi');
            $table->unsignedTinyInteger('w_ip_cluster')->default(20)->after('w_beacon');
            $table->unsignedTinyInteger('w_speed')->default(20)->after('w_ip_cluster');
            $table->unsignedTinyInteger('w_peer_scan')->default(20)->after('w_speed');
            $table->unsignedTinyInteger('w_biometric')->default(20)->after('w_peer_scan');
        });
    }

    public function down(): void
    {
        Schema::table('security_policies', function (Blueprint $table) {
            $table->dropColumn([
                'w_gps', 'w_device', 'w_clock_skew', 'w_wifi', 'w_beacon',
                'w_ip_cluster', 'w_speed', 'w_peer_scan', 'w_biometric',
            ]);
        });
    }
};
```

- [ ] **Step 1.2 — Run the migration**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan migrate --no-interaction
```

Expected output includes: `Running migrations... 2026_05_29_120000_add_proxy_signal_weights_to_security_policies`

- [ ] **Step 1.3 — Update `SecurityPolicy` model**

Replace `$fillable` in `app/Models/SecurityPolicy.php`:

```php
protected $fillable = [
    'policy_name',
    'qr_expiry_seconds',
    'risk_auto_reject',
    'risk_pending_review',
    'late_threshold_mins',
    'geofence_radius_m',
    'device_binding_required',
    'clock_skew_seconds',
    'is_active',
    'w_gps',
    'w_device',
    'w_clock_skew',
    'w_wifi',
    'w_beacon',
    'w_ip_cluster',
    'w_speed',
    'w_peer_scan',
    'w_biometric',
];
```

- [ ] **Step 1.4 — Update `SecurityPolicyFactory`**

Replace the `definition()` method in `database/factories/SecurityPolicyFactory.php`:

```php
public function definition(): array
{
    return [
        'policy_name'             => 'default',
        'qr_expiry_seconds'       => 30,
        'risk_auto_reject'        => 80,
        'risk_pending_review'     => 50,
        'late_threshold_mins'     => 10,
        'geofence_radius_m'       => 50,
        'device_binding_required' => true,
        'clock_skew_seconds'      => 5,
        'is_active'               => true,
        'w_gps'                   => 20,
        'w_device'                => 20,
        'w_clock_skew'            => 20,
        'w_wifi'                  => 20,
        'w_beacon'                => 20,
        'w_ip_cluster'            => 20,
        'w_speed'                 => 20,
        'w_peer_scan'             => 20,
        'w_biometric'             => 20,
    ];
}
```

- [ ] **Step 1.5 — Run Pint**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
```

- [ ] **Step 1.6 — Commit**

```bash
git add database/migrations/ app/Models/SecurityPolicy.php database/factories/SecurityPolicyFactory.php
git commit -m "feat: add proxy signal weight columns to security_policies"
```

---

## Task 2 — Security Policy Form: Proxy Signal Weights Section + Tests

**Files:**
- Modify: `app/Filament/SuperAdmin/Resources/SecurityPolicies/Schemas/SecurityPolicyForm.php`
- Modify: `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/EditSecurityPolicy.php`
- Modify: `tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php`

- [ ] **Step 2.1 — Write the failing tests**

Append to `tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php`:

```php
test('proxy_signal_weights_can_be_saved', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm([
            'w_gps'        => 30,
            'w_device'     => 25,
            'w_clock_skew' => 15,
            'w_wifi'       => 10,
            'w_beacon'     => 10,
            'w_ip_cluster' => 5,
            'w_speed'      => 5,
            'w_peer_scan'  => 0,
            'w_biometric'  => 0,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(SecurityPolicy::class, [
        'id'    => $policy->id,
        'w_gps' => 30,
        'w_biometric' => 0,
    ]);
});

test('proxy_signal_weight_rejects_value_above_100', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['w_gps' => 101])
        ->call('save')
        ->assertHasFormErrors(['w_gps']);
});
```

- [ ] **Step 2.2 — Run tests to confirm they fail**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter="proxy_signal_weight"
```

Expected: 2 failing tests (fields not yet in form).

- [ ] **Step 2.3 — Update `SecurityPolicyForm`**

Replace the full contents of `app/Filament/SuperAdmin/Resources/SecurityPolicies/Schemas/SecurityPolicyForm.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SecurityPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('General Settings')
                ->schema([
                    TextInput::make('policy_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('qr_expiry_seconds')
                        ->label('QR Expiry (seconds)')
                        ->numeric()
                        ->required()
                        ->minValue(10)
                        ->maxValue(300),
                    TextInput::make('risk_auto_reject')
                        ->label('Auto-Reject Score (50–100)')
                        ->numeric()
                        ->required()
                        ->minValue(50)
                        ->maxValue(100),
                    TextInput::make('risk_pending_review')
                        ->label('Pending Review Score (20–79)')
                        ->numeric()
                        ->required()
                        ->minValue(20)
                        ->maxValue(79),
                    TextInput::make('late_threshold_mins')
                        ->label('Late Threshold (minutes)')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    TextInput::make('geofence_radius_m')
                        ->label('Geofence Radius (metres)')
                        ->numeric()
                        ->required()
                        ->minValue(10),
                    TextInput::make('clock_skew_seconds')
                        ->label('Clock Skew Tolerance (seconds)')
                        ->numeric()
                        ->required()
                        ->minValue(0),
                    Toggle::make('device_binding_required'),
                    Toggle::make('is_active'),
                ]),

            Section::make('Proxy Signal Weights')
                ->description('Each weight (0–100) determines how much a signal contributes to the proxy risk score.')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('w_gps')
                                ->label('GPS Location')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('w_device')
                                ->label('Device Fingerprint')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('w_clock_skew')
                                ->label('Clock Skew')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('w_wifi')
                                ->label('WiFi SSID')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('w_beacon')
                                ->label('Bluetooth Beacon')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('w_ip_cluster')
                                ->label('IP Cluster')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('w_speed')
                                ->label('Movement Speed')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('w_peer_scan')
                                ->label('Peer Scan')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('w_biometric')
                                ->label('Biometric')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->maxValue(100),
                        ]),
                ]),
        ]);
    }
}
```

- [ ] **Step 2.4 — Extend audit snapshot in `EditSecurityPolicy`**

In `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/EditSecurityPolicy.php`, the `beforeSave` and `afterSave` snapshots need the 9 new fields. Replace both `->only([...])` calls:

```php
protected function beforeSave(): void
{
    $this->oldValues = $this->record->only([
        'qr_expiry_seconds', 'risk_auto_reject', 'risk_pending_review',
        'late_threshold_mins', 'geofence_radius_m', 'device_binding_required',
        'clock_skew_seconds', 'is_active',
        'w_gps', 'w_device', 'w_clock_skew', 'w_wifi', 'w_beacon',
        'w_ip_cluster', 'w_speed', 'w_peer_scan', 'w_biometric',
    ]);
}

protected function afterSave(): void
{
    Cache::forget('security_policy.active');

    $this->logAudit(
        'security_policy.updated',
        $this->record,
        $this->oldValues,
        $this->record->only([
            'qr_expiry_seconds', 'risk_auto_reject', 'risk_pending_review',
            'late_threshold_mins', 'geofence_radius_m', 'device_binding_required',
            'clock_skew_seconds', 'is_active',
            'w_gps', 'w_device', 'w_clock_skew', 'w_wifi', 'w_beacon',
            'w_ip_cluster', 'w_speed', 'w_peer_scan', 'w_biometric',
        ])
    );
}
```

- [ ] **Step 2.5 — Run all SecurityPolicy tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=SecurityPolicyResourceTest
```

Expected: 8 tests passing.

- [ ] **Step 2.6 — Run Pint**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2.7 — Commit**

```bash
git add app/Filament/SuperAdmin/Resources/SecurityPolicies/ tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php
git commit -m "feat: add proxy signal weights section to SecurityPolicy form"
```

---

## Task 3 — Admin Users: Reinstate Action + Tests

**Files:**
- Modify: `app/Filament/SuperAdmin/Resources/AdminUsers/Tables/AdminUsersTable.php`
- Modify: `tests/Feature/SuperAdmin/AdminUserResourceTest.php`

- [ ] **Step 3.1 — Write the failing tests**

Append to `tests/Feature/SuperAdmin/AdminUserResourceTest.php`:

```php
test('super_admin_can_reinstate_suspended_user', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('reinstate')->table($adminUser))
        ->assertSuccessful();

    expect($adminUser->fresh()->status)->toBe(UserStatus::Active);
});

test('reinstate_action_writes_audit_log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('reinstate')->table($adminUser));

    expect(
        AuditLog::where('action', 'user.reinstated')
            ->where('entity_id', $adminUser->id)
            ->exists()
    )->toBeTrue();
});
```

Make sure these imports are at the top of the test file (they should already be there from existing tests):
- `use App\Enums\UserStatus;`
- `use App\Models\AuditLog;`
- `use Filament\Actions\Testing\TestAction;`

- [ ] **Step 3.2 — Run tests to confirm they fail**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter="reinstate"
```

Expected: 2 failing tests (action not yet defined).

- [ ] **Step 3.3 — Add the `reinstate` action**

In `app/Filament/SuperAdmin/Resources/AdminUsers/Tables/AdminUsersTable.php`, add the `reinstate` action after the existing `suspend` action inside `->recordActions([...])`:

```php
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
    }),
```

The `suspend` action already has all the required imports. No new imports are needed.

- [ ] **Step 3.4 — Run all AdminUser tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=AdminUserResourceTest
```

Expected: 9 tests passing.

- [ ] **Step 3.5 — Run Pint**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3.6 — Commit**

```bash
git add app/Filament/SuperAdmin/Resources/AdminUsers/Tables/AdminUsersTable.php tests/Feature/SuperAdmin/AdminUserResourceTest.php
git commit -m "feat: add reinstate action to Admin Users table"
```

---

## Task 4 — Departments: Card Grid Page + Tests

**Files:**
- Rewrite: `app/Filament/SuperAdmin/Resources/Departments/Pages/ListDepartments.php`
- Create: `resources/views/filament/super-admin/pages/list-departments.blade.php`
- Modify: `tests/Feature/SuperAdmin/DepartmentResourceTest.php`

- [ ] **Step 4.1 — Update the list + delete tests first**

In `tests/Feature/SuperAdmin/DepartmentResourceTest.php`, replace the two tests that depend on the table:

Replace `test('super_admin_can_list_departments', ...)`:

```php
test('super_admin_can_list_departments', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $departments = Department::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListDepartments::class)
        ->assertSee($departments->first()->name)
        ->assertSee($departments->last()->name);
});
```

Replace `test('super_admin_can_delete_department', ...)`:

```php
test('super_admin_can_delete_department', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create();

    $this->actingAs($superAdmin);

    livewire(ListDepartments::class)
        ->call('deleteDepartment', $dept->id)
        ->assertSuccessful();

    \Pest\Laravel\assertDatabaseMissing(Department::class, ['id' => $dept->id]);
});
```

The remaining 4 tests (create, edit, name required, code unique) are unchanged.

- [ ] **Step 4.2 — Run tests to confirm the two rewritten tests fail**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=DepartmentResourceTest
```

Expected: `super_admin_can_list_departments` and `super_admin_can_delete_department` fail (page not yet a card grid). Other 4 should still pass.

- [ ] **Step 4.3 — Rewrite `ListDepartments`**

Replace the full contents of `app/Filament/SuperAdmin/Resources/Departments/Pages/ListDepartments.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\Departments\Pages;

use App\Filament\SuperAdmin\Resources\Departments\DepartmentResource;
use App\Models\Department;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class ListDepartments extends Page
{
    protected static string $resource = DepartmentResource::class;

    protected string $view = 'filament.super-admin.pages.list-departments';

    #[Computed]
    public function departments(): Collection
    {
        return Department::withCount(['students', 'faculty'])
            ->with('headFaculty.user')
            ->orderBy('name')
            ->get();
    }

    public function deleteDepartment(int $id): void
    {
        Department::findOrFail($id)->delete();
        Notification::make()->title('Department deleted')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Department')
                ->url(DepartmentResource::getUrl('create'))
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
```

- [ ] **Step 4.4 — Create the Blade card grid view**

Create `resources/views/filament/super-admin/pages/list-departments.blade.php`:

```blade
<x-filament-panels::page>
    @if ($this->departments->isEmpty())
        <div class="flex flex-col items-center justify-center py-16 text-gray-500 dark:text-gray-400">
            <p class="text-base font-medium">No departments yet.</p>
            <p class="text-sm mt-1">Use the "New Department" button above to create the first one.</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->departments as $dept)
                <x-filament::section>
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                {{ $dept->name }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $dept->code }}</p>
                        </div>
                        <x-filament::badge :color="$dept->is_active ? 'success' : 'danger'" class="shrink-0">
                            {{ $dept->is_active ? 'Active' : 'Inactive' }}
                        </x-filament::badge>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Head of Faculty</dt>
                        <dd class="text-gray-900 dark:text-white">
                            {{ $dept->headFaculty?->user?->name ?? '—' }}
                        </dd>
                        <dt class="text-gray-500 dark:text-gray-400">Students</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $dept->students_count }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Faculty</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $dept->faculty_count }}</dd>
                    </dl>

                    <div class="mt-4 flex items-center gap-3">
                        <a
                            href="{{ \App\Filament\SuperAdmin\Resources\Departments\DepartmentResource::getUrl('edit', ['record' => $dept->id]) }}"
                            class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                        >
                            <x-heroicon-o-pencil-square class="h-4 w-4" />
                            Edit
                        </a>
                        <button
                            wire:click="deleteDepartment({{ $dept->id }})"
                            wire:confirm="Delete department '{{ $dept->name }}'? This cannot be undone."
                            class="inline-flex items-center gap-1 text-sm font-medium text-danger-600 hover:text-danger-500 dark:text-danger-400"
                        >
                            <x-heroicon-o-trash class="h-4 w-4" />
                            Delete
                        </button>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
```

- [ ] **Step 4.5 — Run all Department tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=DepartmentResourceTest
```

Expected: 6 tests passing.

- [ ] **Step 4.6 — Run Pint**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4.7 — Commit**

```bash
git add app/Filament/SuperAdmin/Resources/Departments/Pages/ListDepartments.php \
        resources/views/filament/super-admin/pages/list-departments.blade.php \
        tests/Feature/SuperAdmin/DepartmentResourceTest.php
git commit -m "feat: replace Departments table with card grid view"
```

---

## Task 5 — Dashboard: Security Posture Checklist + Tests

**Files:**
- Modify: `app/Filament/SuperAdmin/Widgets/SystemHealthWidget.php`
- Modify: `resources/views/filament/super-admin/widgets/system-health-widget.blade.php`
- Modify: `tests/Feature/SuperAdmin/DashboardWidgetTest.php`

- [ ] **Step 5.1 — Write the failing test**

Append to `tests/Feature/SuperAdmin/DashboardWidgetTest.php`:

```php
test('system_health_widget_shows_security_posture_checklist', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    SecurityPolicy::factory()->create([
        'device_binding_required' => true,
        'risk_auto_reject'        => 80,
        'geofence_radius_m'       => 50,
        'qr_expiry_seconds'       => 30,
        'is_active'               => true,
    ]);

    $this->actingAs($superAdmin);

    livewire(SystemHealthWidget::class)
        ->assertSee('Device Binding')
        ->assertSee('Auto-Reject Threshold')
        ->assertSee('Geofence')
        ->assertSee('QR Expiry');
});
```

Add the missing import at the top of `tests/Feature/SuperAdmin/DashboardWidgetTest.php`:

```php
use App\Models\SecurityPolicy;
```

- [ ] **Step 5.2 — Run test to confirm it fails**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter="system_health_widget_shows_security_posture_checklist"
```

Expected: FAIL — the strings are not yet rendered.

- [ ] **Step 5.3 — Update `SystemHealthWidget::getViewData()`**

Replace the full contents of `app/Filament/SuperAdmin/Widgets/SystemHealthWidget.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\DataRetentionPolicy;
use App\Models\SecurityPolicy;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class SystemHealthWidget extends Widget
{
    protected string $view = 'filament.super-admin.widgets.system-health-widget';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $policy = SecurityPolicy::getActive();
        $lastRetentionRun = DataRetentionPolicy::whereNotNull('last_run_at')->max('last_run_at');
        $redisConnected = $this->checkRedisConnection();

        return [
            'policy'             => $policy,
            'lastRetentionRun'   => $lastRetentionRun,
            'redisConnected'     => $redisConnected,
            'queueWorkerRunning' => true,
            // Security posture checklist
            'deviceBindingEnabled'  => $policy?->device_binding_required === true,
            'autoRejectConfigured'  => ($policy?->risk_auto_reject ?? 0) >= 50,
            'geofenceConfigured'    => ($policy?->geofence_radius_m ?? 0) > 0,
            'qrExpiryShort'         => ($policy?->qr_expiry_seconds ?? PHP_INT_MAX) <= 60,
        ];
    }

    private function checkRedisConnection(): bool
    {
        try {
            Cache::store('redis')->put('_health_ping', 1, 1);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
```

- [ ] **Step 5.4 — Update the Blade view**

Replace the full contents of `resources/views/filament/super-admin/widgets/system-health-widget.blade.php`:

```blade
<x-filament::section heading="System Health & Security Posture">
    <div class="space-y-4">
        {{-- Infrastructure health --}}
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <p class="font-semibold text-gray-500 dark:text-gray-400">Cache</p>
                <p class="{{ $redisConnected ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $redisConnected ? 'Connected' : 'Unavailable' }}
                </p>
            </div>
            <div>
                <p class="font-semibold text-gray-500 dark:text-gray-400">Active Security Policy</p>
                <p class="text-gray-900 dark:text-white">{{ $policy?->policy_name ?? 'None' }}</p>
            </div>
            <div>
                <p class="font-semibold text-gray-500 dark:text-gray-400">Last Retention Run</p>
                <p class="text-gray-900 dark:text-white">
                    {{ $lastRetentionRun ? \Carbon\Carbon::parse($lastRetentionRun)->diffForHumans() : 'Never' }}
                </p>
            </div>
        </div>

        <hr class="border-gray-200 dark:border-gray-700" />

        {{-- Security posture checklist --}}
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Security Posture
            </p>
            <ul class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                @php
                    $checks = [
                        'Device Binding'        => $deviceBindingEnabled,
                        'Auto-Reject Threshold' => $autoRejectConfigured,
                        'Geofence'              => $geofenceConfigured,
                        'QR Expiry'             => $qrExpiryShort,
                    ];
                @endphp
                @foreach ($checks as $label => $passing)
                    <li class="flex items-center gap-1.5">
                        @if ($passing)
                            <span class="text-green-500">✓</span>
                            <span class="text-gray-700 dark:text-gray-300">{{ $label }}</span>
                        @else
                            <span class="text-red-500">✗</span>
                            <span class="text-gray-500 line-through">{{ $label }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-filament::section>
```

- [ ] **Step 5.5 — Run all Dashboard widget tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=DashboardWidgetTest
```

Expected: 7 tests passing (6 existing + 1 new).

- [ ] **Step 5.6 — Run Pint**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5.7 — Commit**

```bash
git add app/Filament/SuperAdmin/Widgets/SystemHealthWidget.php \
        resources/views/filament/super-admin/widgets/system-health-widget.blade.php \
        tests/Feature/SuperAdmin/DashboardWidgetTest.php
git commit -m "feat: add security posture checklist to SystemHealth widget"
```

---

## Task 6 — Full Test Suite

- [ ] **Step 6.1 — Run full Super Admin test suite**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=SuperAdmin
```

Expected: all tests passing across all 8 SuperAdmin test files.

- [ ] **Step 6.2 — Run the full project test suite**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
```

Expected: full green. If any unrelated test fails, investigate before proceeding.
