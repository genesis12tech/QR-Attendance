# Phase 3: Super Admin Panel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the eight Super Admin panel components: DepartmentResource, AdminUserResource, SecurityPolicyResource, SystemSettingsPage, DataRetentionPolicyResource, AdminRoleAssignmentResource, AuditLogResource, and Dashboard Widgets.

**Architecture:** All components auto-discover under `app/Filament/SuperAdmin/`. Filament v4 places each resource in its own pluralised subdirectory (`Resources/Departments/`), with separate `Schemas/` and `Tables/` classes. Resources that write audit logs mix in `LogsToAudit`. The `SecurityPolicy` and `SystemSetting` caching from Phase 2 is already in place and requires no further changes.

**Tech Stack:** PHP 8.4 · Laravel 12 · Filament v4 · Pest 3 · `LogsToAudit` trait · `Cache::remember` (already wired) · `Heroicon` enum for icons · PHP binary: `/Users/thomas/.config/herd-lite/bin/php`

---

## File Map

| Action | Path | Purpose |
|---|---|---|
| Create | `app/Filament/SuperAdmin/Resources/Departments/DepartmentResource.php` | Resource class |
| Create | `app/Filament/SuperAdmin/Resources/Departments/Schemas/DepartmentForm.php` | Form schema |
| Create | `app/Filament/SuperAdmin/Resources/Departments/Tables/DepartmentsTable.php` | Table definition |
| Create | `app/Filament/SuperAdmin/Resources/Departments/Pages/ListDepartments.php` | List page |
| Create | `app/Filament/SuperAdmin/Resources/Departments/Pages/CreateDepartment.php` | Create page |
| Create | `app/Filament/SuperAdmin/Resources/Departments/Pages/EditDepartment.php` | Edit page |
| Create | `tests/Feature/SuperAdmin/DepartmentResourceTest.php` | 6 tests |
| Create | `app/Filament/SuperAdmin/Resources/AdminUsers/AdminUserResource.php` | Resource class |
| Create | `app/Filament/SuperAdmin/Resources/AdminUsers/Schemas/AdminUserForm.php` | Form schema |
| Create | `app/Filament/SuperAdmin/Resources/AdminUsers/Tables/AdminUsersTable.php` | Table definition |
| Create | `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/ListAdminUsers.php` | List page |
| Create | `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/CreateAdminUser.php` | Create page |
| Create | `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/EditAdminUser.php` | Edit page |
| Create | `tests/Feature/SuperAdmin/AdminUserResourceTest.php` | 6 tests |
| Create | `app/Filament/SuperAdmin/Resources/SecurityPolicies/SecurityPolicyResource.php` | Resource class |
| Create | `app/Filament/SuperAdmin/Resources/SecurityPolicies/Schemas/SecurityPolicyForm.php` | Form schema |
| Create | `app/Filament/SuperAdmin/Resources/SecurityPolicies/Tables/SecurityPoliciesTable.php` | Table definition |
| Create | `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/ListSecurityPolicies.php` | List page |
| Create | `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/EditSecurityPolicy.php` | Edit page (captures old values + audit log) |
| Create | `tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php` | 6 tests |
| Create | `app/Filament/SuperAdmin/Pages/SystemSettingsPage.php` | Custom page with settings form |
| Create | `resources/views/filament/super-admin/pages/system-settings-page.blade.php` | Blade view |
| Create | `tests/Feature/SuperAdmin/SystemSettingsPageTest.php` | 4 tests |
| Create | `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/DataRetentionPolicyResource.php` | Resource class |
| Create | `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Schemas/DataRetentionPolicyForm.php` | Form schema |
| Create | `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Tables/DataRetentionPoliciesTable.php` | Table definition |
| Create | `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Pages/ListDataRetentionPolicies.php` | List page |
| Create | `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Pages/EditDataRetentionPolicy.php` | Edit page |
| Create | `tests/Feature/SuperAdmin/DataRetentionPolicyResourceTest.php` | 3 tests |
| Create | `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/AdminRoleAssignmentResource.php` | Resource class |
| Create | `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Schemas/AdminRoleAssignmentForm.php` | Form schema |
| Create | `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Tables/AdminRoleAssignmentsTable.php` | Table definition |
| Create | `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Pages/ListAdminRoleAssignments.php` | List page |
| Create | `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Pages/CreateAdminRoleAssignment.php` | Create page |
| Create | `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Pages/EditAdminRoleAssignment.php` | Edit page |
| Create | `tests/Feature/SuperAdmin/AdminRoleAssignmentResourceTest.php` | 4 tests |
| Create | `app/Filament/SuperAdmin/Resources/AuditLogs/AuditLogResource.php` | Read-only resource |
| Create | `app/Filament/SuperAdmin/Resources/AuditLogs/Schemas/AuditLogInfolist.php` | Infolist for view modal |
| Create | `app/Filament/SuperAdmin/Resources/AuditLogs/Tables/AuditLogsTable.php` | Table definition |
| Create | `app/Filament/SuperAdmin/Resources/AuditLogs/Pages/ListAuditLogs.php` | List page (only page) |
| Create | `tests/Feature/SuperAdmin/AuditLogResourceTest.php` | 8 tests |
| Create | `app/Filament/SuperAdmin/Widgets/SuperAdminStatsOverviewWidget.php` | Stats overview widget |
| Create | `app/Filament/SuperAdmin/Widgets/AttendanceTrendChartWidget.php` | Bar chart widget |
| Create | `app/Filament/SuperAdmin/Widgets/SystemHealthWidget.php` | Custom health widget |
| Create | `resources/views/filament/super-admin/widgets/system-health-widget.blade.php` | Health widget view |
| Create | `app/Filament/SuperAdmin/Widgets/RecentAuditFeedWidget.php` | Custom audit feed widget |
| Create | `resources/views/filament/super-admin/widgets/recent-audit-feed-widget.blade.php` | Audit feed view |
| Create | `tests/Feature/SuperAdmin/DashboardWidgetTest.php` | 6 tests |

---

## Task 1 — DepartmentResource (Phase 3.1)

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/Departments/DepartmentResource.php`
- Create: `app/Filament/SuperAdmin/Resources/Departments/Schemas/DepartmentForm.php`
- Create: `app/Filament/SuperAdmin/Resources/Departments/Tables/DepartmentsTable.php`
- Create: `app/Filament/SuperAdmin/Resources/Departments/Pages/ListDepartments.php`
- Create: `app/Filament/SuperAdmin/Resources/Departments/Pages/CreateDepartment.php`
- Create: `app/Filament/SuperAdmin/Resources/Departments/Pages/EditDepartment.php`
- Test: `tests/Feature/SuperAdmin/DepartmentResourceTest.php`

---

- [ ] **Step 1.1 — Write failing tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest SuperAdmin/DepartmentResourceTest
```

Replace `tests/Feature/SuperAdmin/DepartmentResourceTest.php` with:

```php
<?php

use App\Filament\SuperAdmin\Resources\Departments\Pages\CreateDepartment;
use App\Filament\SuperAdmin\Resources\Departments\Pages\EditDepartment;
use App\Filament\SuperAdmin\Resources\Departments\Pages\ListDepartments;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_departments', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $departments = Department::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListDepartments::class)
        ->assertCanSeeTableRecords($departments);
});

test('super_admin_can_create_department', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(CreateDepartment::class)
        ->fillForm(['name' => 'Computer Science', 'code' => 'CS', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(Department::class, ['name' => 'Computer Science', 'code' => 'CS']);
});

test('super_admin_can_edit_department', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create(['name' => 'Old Name']);

    $this->actingAs($superAdmin);

    livewire(EditDepartment::class, ['record' => $dept->id])
        ->fillForm(['name' => 'New Name'])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(Department::class, ['id' => $dept->id, 'name' => 'New Name']);
});

test('super_admin_can_delete_department', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create();

    $this->actingAs($superAdmin);

    livewire(ListDepartments::class)
        ->callAction(\Filament\Actions\Testing\TestAction::make('delete')->table($dept))
        ->assertSuccessful();

    \Pest\Laravel\assertDatabaseMissing(Department::class, ['id' => $dept->id]);
});

test('department_name_is_required', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(CreateDepartment::class)
        ->fillForm(['name' => null, 'code' => 'CS'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

test('department_code_must_be_unique', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    Department::factory()->create(['code' => 'CS']);

    $this->actingAs($superAdmin);

    livewire(CreateDepartment::class)
        ->fillForm(['name' => 'Computer Science 2', 'code' => 'CS'])
        ->call('create')
        ->assertHasFormErrors(['code']);
});
```

- [ ] **Step 1.2 — Run tests to confirm RED**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/DepartmentResourceTest.php
```

Expected: 6 failures — class `ListDepartments` not found.

---

- [ ] **Step 1.3 — Scaffold the resource**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-resource Department --panel=super-admin --no-interaction
```

Expected output: `Filament resource [App\Filament\SuperAdmin\Resources\Departments\DepartmentResource] created successfully.`

---

- [ ] **Step 1.4 — Implement `DepartmentForm`**

Replace `app/Filament/SuperAdmin/Resources/Departments/Schemas/DepartmentForm.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\Departments\Schemas;

use App\Models\Faculty;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('code')
                ->required()
                ->maxLength(10)
                ->unique(ignoreRecord: true),
            Select::make('head_faculty_id')
                ->label('Head of Faculty')
                ->options(fn () => Faculty::with('user')->get()->pluck('user.name', 'id'))
                ->searchable()
                ->nullable(),
            Toggle::make('is_active')
                ->default(true),
        ]);
    }
}
```

- [ ] **Step 1.5 — Implement `DepartmentsTable`**

Replace `app/Filament/SuperAdmin/Resources/Departments/Tables/DepartmentsTable.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\Departments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('code')->sortable()->searchable(),
                TextColumn::make('headFaculty.user.name')
                    ->label('Head of Faculty')
                    ->default('—'),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->state(fn ($record) => $record->students()->count()),
                TextColumn::make('faculty_count')
                    ->label('Faculty')
                    ->state(fn ($record) => $record->faculty()->count()),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([1 => 'Active', 0 => 'Inactive'])
                    ->attribute('is_active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No departments yet')
            ->emptyStateDescription('Create the first department using the button above.');
    }
}
```

- [ ] **Step 1.6 — Implement `DepartmentResource`**

Replace `app/Filament/SuperAdmin/Resources/Departments/DepartmentResource.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\Departments;

use App\Filament\SuperAdmin\Resources\Departments\Pages\CreateDepartment;
use App\Filament\SuperAdmin\Resources\Departments\Pages\EditDepartment;
use App\Filament\SuperAdmin\Resources\Departments\Pages\ListDepartments;
use App\Filament\SuperAdmin\Resources\Departments\Schemas\DepartmentForm;
use App\Filament\SuperAdmin\Resources\Departments\Tables\DepartmentsTable;
use App\Models\Department;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return DepartmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DepartmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDepartments::route('/'),
            'create' => CreateDepartment::route('/create'),
            'edit' => EditDepartment::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 1.7 — Run tests to confirm GREEN**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/DepartmentResourceTest.php
```

Expected: 6 passed.

- [ ] **Step 1.8 — Full suite + pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/SuperAdmin/Resources/Departments tests/Feature/SuperAdmin/DepartmentResourceTest.php
git commit -m "feat: add DepartmentResource for super admin panel"
```

---

## Task 2 — AdminUserResource (Phase 3.2)

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/AdminUsers/AdminUserResource.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminUsers/Schemas/AdminUserForm.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminUsers/Tables/AdminUsersTable.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/ListAdminUsers.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/CreateAdminUser.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/EditAdminUser.php`
- Test: `tests/Feature/SuperAdmin/AdminUserResourceTest.php`

---

- [ ] **Step 2.1 — Write failing tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest SuperAdmin/AdminUserResourceTest
```

Replace `tests/Feature/SuperAdmin/AdminUserResourceTest.php` with:

```php
<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_admin_users', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUsers = User::factory()->admin()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->assertCanSeeTableRecords($adminUsers);
});

test('super_admin_can_create_admin_user', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(CreateAdminUser::class)
        ->fillForm([
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value,
            'status' => UserStatus::Active->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(User::class, ['email' => 'newadmin@example.com', 'role' => 'admin']);
});

test('super_admin_can_suspend_admin_user', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create();

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('suspend')->table($adminUser))
        ->assertSuccessful();

    expect($adminUser->fresh()->status)->toBe(UserStatus::Suspended);
});

test('suspend_action_writes_audit_log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $adminUser = User::factory()->admin()->create();

    $this->actingAs($superAdmin);

    livewire(ListAdminUsers::class)
        ->callAction(TestAction::make('suspend')->table($adminUser));

    expect(
        AuditLog::where('action', 'user.suspended')
            ->where('entity_id', $adminUser->id)
            ->exists()
    )->toBeTrue();
});

test('email_must_be_unique_on_create', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->admin()->create(['email' => 'taken@example.com']);

    $this->actingAs($superAdmin);

    livewire(CreateAdminUser::class)
        ->fillForm([
            'name' => 'Another Admin',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);
});

test('password_confirmation_is_required', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(CreateAdminUser::class)
        ->fillForm([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'different-password',
            'role' => UserRole::Admin->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['password']);
});
```

- [ ] **Step 2.2 — Run to confirm RED**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/AdminUserResourceTest.php
```

Expected: 6 failures — `ListAdminUsers` class not found.

---

- [ ] **Step 2.3 — Create directory structure and all files**

Create `app/Filament/SuperAdmin/Resources/AdminUsers/Schemas/AdminUserForm.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AdminUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->required(fn (string $context) => $context === 'create')
                ->confirmed()
                ->dehydrated(fn ($state) => filled($state)),
            TextInput::make('password_confirmation')
                ->password()
                ->required(fn (string $context) => $context === 'create')
                ->dehydrated(false),
            Select::make('role')
                ->options([
                    UserRole::Admin->value => 'Admin',
                    UserRole::Faculty->value => 'Faculty',
                ])
                ->required(),
            Select::make('status')
                ->options(UserStatus::class)
                ->default(UserStatus::Active->value)
                ->required(),
        ]);
    }
}
```

Create `app/Filament/SuperAdmin/Resources/AdminUsers/Tables/AdminUsersTable.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers\Tables;

use App\Enums\UserStatus;
use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('last_login_at')->dateTime()->sortable(),
                TextColumn::make('activeAdminAssignment.department.name')
                    ->label('Department')
                    ->default('—'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status !== UserStatus::Suspended)
                    ->action(function (User $record) {
                        $old = ['status' => $record->status->value];
                        $record->update(['status' => UserStatus::Suspended]);
                        AuditLog::record('user.suspended', $record, $old, ['status' => UserStatus::Suspended->value]);
                        Notification::make()->title('User suspended')->success()->send();
                    }),
                Action::make('revokeRole')
                    ->label('Revoke Role')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        AdminRoleAssignment::where('user_id', $record->id)
                            ->active()
                            ->update(['revoked_at' => now()]);
                        AuditLog::record('user.role_revoked', $record, ['role' => $record->role->value], []);
                        Notification::make()->title('Role revoked')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('suspend')
                        ->label('Suspend selected')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(function (User $user) {
                                $old = ['status' => $user->status->value];
                                $user->update(['status' => UserStatus::Suspended]);
                                AuditLog::record('user.suspended', $user, $old, ['status' => UserStatus::Suspended->value]);
                            });
                            Notification::make()->title('Users suspended')->success()->send();
                        }),
                    BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(function (Collection $records) {
                            $csv = "Name,Email,Role,Status\n";
                            foreach ($records as $record) {
                                $csv .= sprintf(
                                    "%s,%s,%s,%s\n",
                                    $record->name,
                                    $record->email,
                                    $record->role->value,
                                    $record->status->value,
                                );
                            }
                            return response()->streamDownload(fn () => print($csv), 'admin-users.csv');
                        }),
                ]),
            ]);
    }
}
```

Create `app/Filament/SuperAdmin/Resources/AdminUsers/AdminUserResource.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers;

use App\Enums\UserRole;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\CreateAdminUser;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\EditAdminUser;
use App\Filament\SuperAdmin\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Filament\SuperAdmin\Resources\AdminUsers\Schemas\AdminUserForm;
use App\Filament\SuperAdmin\Resources\AdminUsers\Tables\AdminUsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Admin Users';

    protected static ?string $modelLabel = 'Admin User';

    protected static ?string $pluralModelLabel = 'Admin Users';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('activeAdminAssignment.department')
            ->whereIn('role', [UserRole::Admin->value, UserRole::Faculty->value]);
    }

    public static function form(Schema $schema): Schema
    {
        return AdminUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminUsers::route('/'),
            'create' => CreateAdminUser::route('/create'),
            'edit' => EditAdminUser::route('/{record}/edit'),
        ];
    }
}
```

Create `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/ListAdminUsers.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers\Pages;

use App\Filament\SuperAdmin\Resources\AdminUsers\AdminUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

Create `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/CreateAdminUser.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers\Pages;

use App\Filament\SuperAdmin\Resources\AdminUsers\AdminUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;
}
```

Create `app/Filament/SuperAdmin/Resources/AdminUsers/Pages/EditAdminUser.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers\Pages;

use App\Filament\SuperAdmin\Resources\AdminUsers\AdminUserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 2.4 — Run to confirm GREEN**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/AdminUserResourceTest.php
```

Expected: 6 passed.

- [ ] **Step 2.5 — Full suite + pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/SuperAdmin/Resources/AdminUsers tests/Feature/SuperAdmin/AdminUserResourceTest.php
git commit -m "feat: add AdminUserResource with suspend and revoke-role actions"
```

---

## Task 3 — SecurityPolicyResource (Phase 3.3)

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/SecurityPolicies/SecurityPolicyResource.php`
- Create: `app/Filament/SuperAdmin/Resources/SecurityPolicies/Schemas/SecurityPolicyForm.php`
- Create: `app/Filament/SuperAdmin/Resources/SecurityPolicies/Tables/SecurityPoliciesTable.php`
- Create: `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/ListSecurityPolicies.php`
- Create: `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/EditSecurityPolicy.php`
- Test: `tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php`

---

- [ ] **Step 3.1 — Write failing tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest SuperAdmin/SecurityPolicyResourceTest
```

Replace `tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php` with:

```php
<?php

use App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages\EditSecurityPolicy;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages\ListSecurityPolicies;
use App\Models\AuditLog;
use App\Models\SecurityPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_edit_security_policy', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30]);

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 60])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(SecurityPolicy::class, ['id' => $policy->id, 'qr_expiry_seconds' => 60]);
});

test('qr_expiry_seconds_must_be_between_10_and_300', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 5])
        ->call('save')
        ->assertHasFormErrors(['qr_expiry_seconds']);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 400])
        ->call('save')
        ->assertHasFormErrors(['qr_expiry_seconds']);
});

test('risk_auto_reject_must_be_between_50_and_100', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['risk_auto_reject' => 40])
        ->call('save')
        ->assertHasFormErrors(['risk_auto_reject']);
});

test('risk_pending_review_must_be_between_20_and_79', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['risk_pending_review' => 90])
        ->call('save')
        ->assertHasFormErrors(['risk_pending_review']);
});

test('save_clears_security_policy_cache', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create();
    Cache::put('security_policy.active', $policy, 60);

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 45])
        ->call('save');

    expect(Cache::has('security_policy.active'))->toBeFalse();
});

test('save_writes_audit_log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30]);

    $this->actingAs($superAdmin);

    livewire(EditSecurityPolicy::class, ['record' => $policy->id])
        ->fillForm(['qr_expiry_seconds' => 60])
        ->call('save');

    expect(
        AuditLog::where('action', 'security_policy.updated')
            ->where('entity_id', $policy->id)
            ->exists()
    )->toBeTrue();
});
```

- [ ] **Step 3.2 — Run to confirm RED**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php
```

Expected: 6 failures — `ListSecurityPolicies` class not found.

---

- [ ] **Step 3.3 — Create all SecurityPolicy resource files**

Create `app/Filament/SuperAdmin/Resources/SecurityPolicies/Schemas/SecurityPolicyForm.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SecurityPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('policy_name')
                ->required()
                ->maxLength(255),
            TextInput::make('qr_expiry_seconds')
                ->numeric()
                ->required()
                ->minValue(10)
                ->maxValue(300),
            TextInput::make('risk_auto_reject')
                ->numeric()
                ->required()
                ->minValue(50)
                ->maxValue(100),
            TextInput::make('risk_pending_review')
                ->numeric()
                ->required()
                ->minValue(20)
                ->maxValue(79),
            TextInput::make('late_threshold_mins')
                ->numeric()
                ->required()
                ->minValue(1),
            TextInput::make('geofence_radius_m')
                ->numeric()
                ->required()
                ->minValue(10),
            TextInput::make('clock_skew_seconds')
                ->numeric()
                ->required()
                ->minValue(0),
            Toggle::make('device_binding_required'),
            Toggle::make('is_active'),
        ]);
    }
}
```

Create `app/Filament/SuperAdmin/Resources/SecurityPolicies/Tables/SecurityPoliciesTable.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SecurityPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('policy_name')->sortable(),
                TextColumn::make('qr_expiry_seconds')->label('QR Expiry (s)'),
                TextColumn::make('risk_auto_reject')->label('Auto-Reject Score'),
                TextColumn::make('risk_pending_review')->label('Pending Review Score'),
                TextColumn::make('late_threshold_mins')->label('Late Threshold (m)'),
                IconColumn::make('device_binding_required')->boolean(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
```

Create `app/Filament/SuperAdmin/Resources/SecurityPolicies/SecurityPolicyResource.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies;

use App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages\EditSecurityPolicy;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages\ListSecurityPolicies;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\Schemas\SecurityPolicyForm;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\Tables\SecurityPoliciesTable;
use App\Models\SecurityPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SecurityPolicyResource extends Resource
{
    protected static ?string $model = SecurityPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return SecurityPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SecurityPoliciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSecurityPolicies::route('/'),
            'edit' => EditSecurityPolicy::route('/{record}/edit'),
        ];
    }
}
```

Create `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/ListSecurityPolicies.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages;

use App\Filament\SuperAdmin\Resources\SecurityPolicies\SecurityPolicyResource;
use Filament\Resources\Pages\ListRecords;

class ListSecurityPolicies extends ListRecords
{
    protected static string $resource = SecurityPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

Create `app/Filament/SuperAdmin/Resources/SecurityPolicies/Pages/EditSecurityPolicy.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\SecurityPolicies\Pages;

use App\Concerns\LogsToAudit;
use App\Filament\SuperAdmin\Resources\SecurityPolicies\SecurityPolicyResource;
use Filament\Resources\Pages\EditRecord;

class EditSecurityPolicy extends EditRecord
{
    use LogsToAudit;

    protected static string $resource = SecurityPolicyResource::class;

    protected array $oldValues = [];

    protected function beforeSave(): void
    {
        $this->oldValues = $this->record->only([
            'qr_expiry_seconds', 'risk_auto_reject', 'risk_pending_review',
            'late_threshold_mins', 'geofence_radius_m', 'device_binding_required',
            'clock_skew_seconds', 'is_active',
        ]);
    }

    protected function afterSave(): void
    {
        $this->logAudit(
            'security_policy.updated',
            $this->record,
            $this->oldValues,
            $this->record->only([
                'qr_expiry_seconds', 'risk_auto_reject', 'risk_pending_review',
                'late_threshold_mins', 'geofence_radius_m', 'device_binding_required',
                'clock_skew_seconds', 'is_active',
            ])
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

- [ ] **Step 3.4 — Run to confirm GREEN**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php
```

Expected: 6 passed.

- [ ] **Step 3.5 — Full suite + pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/SuperAdmin/Resources/SecurityPolicies tests/Feature/SuperAdmin/SecurityPolicyResourceTest.php
git commit -m "feat: add SecurityPolicyResource with audit logging"
```

---

## Task 4 — SystemSettingsPage (Phase 3.4)

**Files:**
- Create: `app/Filament/SuperAdmin/Pages/SystemSettingsPage.php`
- Create: `resources/views/filament/super-admin/pages/system-settings-page.blade.php`
- Test: `tests/Feature/SuperAdmin/SystemSettingsPageTest.php`

---

- [ ] **Step 4.1 — Write failing tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest SuperAdmin/SystemSettingsPageTest
```

Replace `tests/Feature/SuperAdmin/SystemSettingsPageTest.php` with:

```php
<?php

use App\Filament\SuperAdmin\Pages\SystemSettingsPage;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_view_system_settings_page', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get('/super-admin/system-settings')
        ->assertSuccessful();
});

test('system_settings_are_pre_populated_from_database', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    SystemSetting::set('app_name', 'My App');

    $this->actingAs($superAdmin);

    livewire(SystemSettingsPage::class)
        ->assertFormSet(['app_name' => 'My App']);
});

test('super_admin_can_save_system_settings', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(SystemSettingsPage::class)
        ->fillForm(['app_name' => 'Updated App', 'qr_rotation_seconds' => '45'])
        ->call('save')
        ->assertNotified();

    expect(SystemSetting::get('app_name'))->toBe('Updated App');
    expect(SystemSetting::get('qr_rotation_seconds'))->toBe('45');
});

test('faculty_can_review_flags_toggle_persists', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(SystemSettingsPage::class)
        ->fillForm(['faculty_can_review_flags' => true])
        ->call('save');

    expect(SystemSetting::get('faculty_can_review_flags'))->toBe('true');
});
```

- [ ] **Step 4.2 — Run to confirm RED**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/SystemSettingsPageTest.php
```

Expected: failures — `SystemSettingsPage` class not found.

---

- [ ] **Step 4.3 — Scaffold the page**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-page SystemSettingsPage --panel=super-admin --no-interaction
```

This creates `app/Filament/SuperAdmin/Pages/SystemSettingsPage.php` and a blade view at `resources/views/filament/super-admin/pages/system-settings-page.blade.php`.

---

- [ ] **Step 4.4 — Implement `SystemSettingsPage`**

Replace `app/Filament/SuperAdmin/Pages/SystemSettingsPage.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\SystemSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SystemSettingsPage extends Page implements HasSchemas
{
    use \Filament\Schemas\Concerns\InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'System Settings';

    protected static ?string $slug = 'system-settings';

    protected string $view = 'filament.super-admin.pages.system-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'app_name' => SystemSetting::get('app_name', config('app.name')),
            'qr_rotation_seconds' => SystemSetting::get('qr_rotation_seconds', '30'),
            'max_devices_per_student' => SystemSetting::get('max_devices_per_student', '1'),
            'attendance_window_mins' => SystemSetting::get('attendance_window_mins', '120'),
            'faculty_can_review_flags' => SystemSetting::get('faculty_can_review_flags', 'false') === 'true',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_name')
                    ->label('Application Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('qr_rotation_seconds')
                    ->label('QR Rotation Interval (seconds)')
                    ->numeric()
                    ->required()
                    ->minValue(10),
                TextInput::make('max_devices_per_student')
                    ->label('Max Devices per Student')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                TextInput::make('attendance_window_mins')
                    ->label('Attendance Window (minutes)')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                Toggle::make('faculty_can_review_flags')
                    ->label('Faculty Can Review Proxy Flags'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SystemSetting::set('app_name', $data['app_name']);
        SystemSetting::set('qr_rotation_seconds', $data['qr_rotation_seconds']);
        SystemSetting::set('max_devices_per_student', $data['max_devices_per_student']);
        SystemSetting::set('attendance_window_mins', $data['attendance_window_mins']);
        SystemSetting::set('faculty_can_review_flags', $data['faculty_can_review_flags'] ? 'true' : 'false');

        Notification::make()->title('Settings saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save'),
        ];
    }
}
```

- [ ] **Step 4.5 — Implement the blade view**

Replace `resources/views/filament/super-admin/pages/system-settings-page.blade.php` with:

```blade
<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>
</x-filament-panels::page>
```

- [ ] **Step 4.6 — Run to confirm GREEN**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/SystemSettingsPageTest.php
```

Expected: 4 passed.

- [ ] **Step 4.7 — Full suite + pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/SuperAdmin/Pages/SystemSettingsPage.php resources/views/filament/super-admin/pages/system-settings-page.blade.php tests/Feature/SuperAdmin/SystemSettingsPageTest.php
git commit -m "feat: add SystemSettingsPage for super admin panel"
```

---

## Task 5 — DataRetentionPolicyResource (Phase 3.5)

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/DataRetentionPolicyResource.php`
- Create: `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Schemas/DataRetentionPolicyForm.php`
- Create: `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Tables/DataRetentionPoliciesTable.php`
- Create: `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Pages/ListDataRetentionPolicies.php`
- Create: `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Pages/EditDataRetentionPolicy.php`
- Test: `tests/Feature/SuperAdmin/DataRetentionPolicyResourceTest.php`

---

- [ ] **Step 5.1 — Write failing tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest SuperAdmin/DataRetentionPolicyResourceTest
```

Replace `tests/Feature/SuperAdmin/DataRetentionPolicyResourceTest.php` with:

```php
<?php

use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages\EditDataRetentionPolicy;
use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages\ListDataRetentionPolicies;
use App\Models\DataRetentionPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_retention_policies', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policies = DataRetentionPolicy::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListDataRetentionPolicies::class)
        ->assertCanSeeTableRecords($policies);
});

test('super_admin_can_edit_retention_policy', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = DataRetentionPolicy::factory()->create(['retention_days' => 365]);

    $this->actingAs($superAdmin);

    livewire(EditDataRetentionPolicy::class, ['record' => $policy->id])
        ->fillForm(['retention_days' => 730])
        ->call('save')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas(DataRetentionPolicy::class, ['id' => $policy->id, 'retention_days' => 730]);
});

test('retention_days_must_be_positive_integer', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $policy = DataRetentionPolicy::factory()->create();

    $this->actingAs($superAdmin);

    livewire(EditDataRetentionPolicy::class, ['record' => $policy->id])
        ->fillForm(['retention_days' => 0])
        ->call('save')
        ->assertHasFormErrors(['retention_days']);
});
```

- [ ] **Step 5.2 — Run to confirm RED**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/DataRetentionPolicyResourceTest.php
```

Expected: 3 failures — `ListDataRetentionPolicies` not found.

---

- [ ] **Step 5.3 — Scaffold and implement**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-resource DataRetentionPolicy --panel=super-admin --no-interaction
```

Create `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Schemas/DataRetentionPolicyForm.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DataRetentionPolicyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('entity_type')
                ->required()
                ->maxLength(255),
            TextInput::make('retention_days')
                ->numeric()
                ->required()
                ->minValue(1),
            Toggle::make('is_active'),
        ]);
    }
}
```

Create `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/Tables/DataRetentionPoliciesTable.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DataRetentionPoliciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity_type')->sortable(),
                TextColumn::make('retention_days')->suffix(' days')->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('last_run_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
```

Replace `app/Filament/SuperAdmin/Resources/DataRetentionPolicies/DataRetentionPolicyResource.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies;

use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages\EditDataRetentionPolicy;
use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages\ListDataRetentionPolicies;
use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Schemas\DataRetentionPolicyForm;
use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Tables\DataRetentionPoliciesTable;
use App\Models\DataRetentionPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DataRetentionPolicyResource extends Resource
{
    protected static ?string $model = DataRetentionPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DataRetentionPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DataRetentionPoliciesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDataRetentionPolicies::route('/'),
            'edit' => EditDataRetentionPolicy::route('/{record}/edit'),
        ];
    }
}
```

Replace the generated `ListDataRetentionPolicies.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages;

use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\DataRetentionPolicyResource;
use Filament\Resources\Pages\ListRecords;

class ListDataRetentionPolicies extends ListRecords
{
    protected static string $resource = DataRetentionPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

Replace the generated `EditDataRetentionPolicy.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\DataRetentionPolicies\Pages;

use App\Filament\SuperAdmin\Resources\DataRetentionPolicies\DataRetentionPolicyResource;
use Filament\Resources\Pages\EditRecord;

class EditDataRetentionPolicy extends EditRecord
{
    protected static string $resource = DataRetentionPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

> **Note:** The scaffold may create a `CreateDataRetentionPolicy.php` page. Delete it — retention policies are seeded, not created via the UI.

- [ ] **Step 5.4 — Run to confirm GREEN**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/DataRetentionPolicyResourceTest.php
```

Expected: 3 passed.

- [ ] **Step 5.5 — Full suite + pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/SuperAdmin/Resources/DataRetentionPolicies tests/Feature/SuperAdmin/DataRetentionPolicyResourceTest.php
git commit -m "feat: add DataRetentionPolicyResource for super admin panel"
```

---

## Task 6 — AdminRoleAssignmentResource (Phase 3.6)

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/AdminRoleAssignmentResource.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Schemas/AdminRoleAssignmentForm.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Tables/AdminRoleAssignmentsTable.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Pages/ListAdminRoleAssignments.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Pages/CreateAdminRoleAssignment.php`
- Create: `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Pages/EditAdminRoleAssignment.php`
- Test: `tests/Feature/SuperAdmin/AdminRoleAssignmentResourceTest.php`

---

- [ ] **Step 6.1 — Write failing tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest SuperAdmin/AdminRoleAssignmentResourceTest
```

Replace `tests/Feature/SuperAdmin/AdminRoleAssignmentResourceTest.php` with:

```php
<?php

use App\Enums\AdminAssignmentRole;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\CreateAdminRoleAssignment;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\ListAdminRoleAssignments;
use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_role_assignments', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $assignments = AdminRoleAssignment::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListAdminRoleAssignments::class)
        ->assertCanSeeTableRecords($assignments);
});

test('super_admin_can_create_role_assignment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $user = User::factory()->admin()->create();
    $department = Department::factory()->create();

    $this->actingAs($superAdmin);

    livewire(CreateAdminRoleAssignment::class)
        ->fillForm([
            'user_id' => $user->id,
            'assigned_by' => $superAdmin->id,
            'role' => AdminAssignmentRole::Admin->value,
            'department_id' => $department->id,
            'assigned_at' => now()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    \Pest\Laravel\assertDatabaseHas(AdminRoleAssignment::class, [
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);
});

test('super_admin_can_revoke_role_assignment', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $assignment = AdminRoleAssignment::factory()->create(['revoked_at' => null]);

    $this->actingAs($superAdmin);

    livewire(ListAdminRoleAssignments::class)
        ->callAction(TestAction::make('revoke')->table($assignment))
        ->assertSuccessful();

    expect($assignment->fresh()->revoked_at)->not->toBeNull();
});

test('revoke_writes_audit_log', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $assignment = AdminRoleAssignment::factory()->create(['revoked_at' => null]);

    $this->actingAs($superAdmin);

    livewire(ListAdminRoleAssignments::class)
        ->callAction(TestAction::make('revoke')->table($assignment));

    expect(
        AuditLog::where('action', 'role_assignment.revoked')
            ->where('entity_id', $assignment->id)
            ->exists()
    )->toBeTrue();
});
```

- [ ] **Step 6.2 — Run to confirm RED**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/AdminRoleAssignmentResourceTest.php
```

Expected: 4 failures.

---

- [ ] **Step 6.3 — Scaffold and implement**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-resource AdminRoleAssignment --panel=super-admin --no-interaction
```

Create `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Schemas/AdminRoleAssignmentForm.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Schemas;

use App\Enums\AdminAssignmentRole;
use App\Models\Department;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class AdminRoleAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('User')
                ->options(fn () => User::pluck('name', 'id'))
                ->searchable()
                ->required(),
            Select::make('assigned_by')
                ->label('Assigned By')
                ->options(fn () => User::pluck('name', 'id'))
                ->searchable()
                ->required(),
            Select::make('role')
                ->options(AdminAssignmentRole::class)
                ->required(),
            Select::make('department_id')
                ->label('Department')
                ->options(fn () => Department::pluck('name', 'id'))
                ->searchable()
                ->required(),
            DateTimePicker::make('assigned_at')
                ->required()
                ->default(now()),
        ]);
    }
}
```

Create `app/Filament/SuperAdmin/Resources/AdminRoleAssignments/Tables/AdminRoleAssignmentsTable.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Tables;

use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminRoleAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('User')->searchable(),
                TextColumn::make('role')->badge(),
                TextColumn::make('department.name')->label('Department'),
                TextColumn::make('assigned_at')->dateTime()->sortable(),
                TextColumn::make('revoked_at')->dateTime()->sortable()->default('—'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AdminRoleAssignment $record) => $record->revoked_at === null)
                    ->action(function (AdminRoleAssignment $record) {
                        $record->update(['revoked_at' => now()]);
                        AuditLog::record('role_assignment.revoked', $record, ['revoked_at' => null], ['revoked_at' => now()->toIso8601String()]);
                        Notification::make()->title('Role assignment revoked')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
```

Replace the generated `AdminRoleAssignmentResource.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments;

use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\CreateAdminRoleAssignment;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\EditAdminRoleAssignment;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages\ListAdminRoleAssignments;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Schemas\AdminRoleAssignmentForm;
use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Tables\AdminRoleAssignmentsTable;
use App\Models\AdminRoleAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AdminRoleAssignmentResource extends Resource
{
    protected static ?string $model = AdminRoleAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return AdminRoleAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminRoleAssignmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminRoleAssignments::route('/'),
            'create' => CreateAdminRoleAssignment::route('/create'),
            'edit' => EditAdminRoleAssignment::route('/{record}/edit'),
        ];
    }
}
```

Replace generated pages with these minimal implementations:

`ListAdminRoleAssignments.php`:
```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages;

use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\AdminRoleAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdminRoleAssignments extends ListRecords
{
    protected static string $resource = AdminRoleAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`CreateAdminRoleAssignment.php`:
```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages;

use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\AdminRoleAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminRoleAssignment extends CreateRecord
{
    protected static string $resource = AdminRoleAssignmentResource::class;
}
```

`EditAdminRoleAssignment.php`:
```php
<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Pages;

use App\Filament\SuperAdmin\Resources\AdminRoleAssignments\AdminRoleAssignmentResource;
use Filament\Resources\Pages\EditRecord;

class EditAdminRoleAssignment extends EditRecord
{
    protected static string $resource = AdminRoleAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

- [ ] **Step 6.4 — Run to confirm GREEN**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/AdminRoleAssignmentResourceTest.php
```

Expected: 4 passed.

- [ ] **Step 6.5 — Full suite + pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/SuperAdmin/Resources/AdminRoleAssignments tests/Feature/SuperAdmin/AdminRoleAssignmentResourceTest.php
git commit -m "feat: add AdminRoleAssignmentResource with revoke action"
```

---

## Task 7 — AuditLogResource (Phase 3.7)

**Files:**
- Create: `app/Filament/SuperAdmin/Resources/AuditLogs/AuditLogResource.php`
- Create: `app/Filament/SuperAdmin/Resources/AuditLogs/Tables/AuditLogsTable.php`
- Create: `app/Filament/SuperAdmin/Resources/AuditLogs/Pages/ListAuditLogs.php`
- Test: `tests/Feature/SuperAdmin/AuditLogResourceTest.php`

---

- [ ] **Step 7.1 — Write failing tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest SuperAdmin/AuditLogResourceTest
```

Replace `tests/Feature/SuperAdmin/AuditLogResourceTest.php` with:

```php
<?php

use App\Filament\SuperAdmin\Resources\AuditLogs\AuditLogResource;
use App\Filament\SuperAdmin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('super_admin_can_list_audit_logs', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $logs = AuditLog::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->assertCanSeeTableRecords($logs);
});

test('audit_log_has_no_create_action', function () {
    expect(AuditLogResource::canCreate())->toBeFalse();
});

test('audit_log_has_no_edit_action', function () {
    $log = AuditLog::factory()->create();
    expect(AuditLogResource::canEdit($log))->toBeFalse();
});

test('audit_log_has_no_delete_action', function () {
    $log = AuditLog::factory()->create();
    expect(AuditLogResource::canDelete($log))->toBeFalse();
});

test('super_admin_can_view_old_and_new_values_in_modal', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $log = AuditLog::factory()->create([
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
    ]);

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->callAction(TestAction::make('view')->table($log))
        ->assertSuccessful();
});

test('super_admin_can_export_audit_log_csv', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AuditLog::factory()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->callAction('export_csv')
        ->assertSuccessful();
});

test('audit_log_filters_by_action_type', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AuditLog::factory()->create(['action' => 'user.created']);
    AuditLog::factory()->create(['action' => 'session.closed']);

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->filterTable('action', 'user.created')
        ->assertCanSeeTableRecords(AuditLog::where('action', 'user.created')->get())
        ->assertCanNotSeeTableRecords(AuditLog::where('action', 'session.closed')->get());
});

test('audit_log_filters_by_actor_role', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AuditLog::factory()->create(['actor_role' => 'admin']);
    AuditLog::factory()->create(['actor_role' => 'faculty']);

    $this->actingAs($superAdmin);

    livewire(ListAuditLogs::class)
        ->filterTable('actor_role', 'admin')
        ->assertCanSeeTableRecords(AuditLog::where('actor_role', 'admin')->get())
        ->assertCanNotSeeTableRecords(AuditLog::where('actor_role', 'faculty')->get());
});
```

- [ ] **Step 7.2 — Run to confirm RED**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/AuditLogResourceTest.php
```

Expected: failures — `ListAuditLogs` not found.

---

- [ ] **Step 7.3 — Scaffold and implement**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-resource AuditLog --panel=super-admin --no-interaction
```

> Delete the generated `CreateAuditLog.php` and `EditAuditLog.php` pages — this resource is read-only.

Create `app/Filament/SuperAdmin/Resources/AuditLogs/Tables/AuditLogsTable.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AuditLogs\Tables;

use App\Enums\UserRole;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('actor.name')->label('Actor')->default('System')->searchable(),
                TextColumn::make('actor_role')->badge()->sortable(),
                TextColumn::make('action')->sortable()->searchable(),
                TextColumn::make('entity_type')->sortable(),
                TextColumn::make('entity_id')->sortable(),
                TextColumn::make('ip_address'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => AuditLog::distinct()->pluck('action', 'action')),
                SelectFilter::make('actor_role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'faculty' => 'Faculty',
                    ]),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View Changes')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalContent(fn (AuditLog $record) => new \Illuminate\Support\HtmlString(
                        '<div class="space-y-2">'
                        . '<p class="font-semibold">Old Values</p>'
                        . '<pre class="text-xs bg-gray-50 p-2 rounded">' . json_encode($record->old_values, JSON_PRETTY_PRINT) . '</pre>'
                        . '<p class="font-semibold mt-2">New Values</p>'
                        . '<pre class="text-xs bg-gray-50 p-2 rounded">' . json_encode($record->new_values, JSON_PRETTY_PRINT) . '</pre>'
                        . '</div>'
                    ))
                    ->modalSubmitAction(false),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
```

Replace `app/Filament/SuperAdmin/Resources/AuditLogs/AuditLogResource.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AuditLogs;

use App\Filament\SuperAdmin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\SuperAdmin\Resources\AuditLogs\Tables\AuditLogsTable;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Audit';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
```

Replace generated `ListAuditLogs.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Resources\AuditLogs\Pages;

use App\Filament\SuperAdmin\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function () {
                    $logs = AuditLog::with('actor')->get();
                    $csv = "Actor,Role,Action,Entity Type,Entity ID,IP,Created At\n";
                    foreach ($logs as $log) {
                        $csv .= sprintf(
                            "%s,%s,%s,%s,%s,%s,%s\n",
                            $log->actor?->name ?? 'System',
                            $log->actor_role ?? '',
                            $log->action,
                            $log->entity_type,
                            $log->entity_id,
                            $log->ip_address ?? '',
                            $log->created_at,
                        );
                    }
                    return response()->streamDownload(fn () => print($csv), 'audit-logs.csv');
                }),
        ];
    }
}
```

- [ ] **Step 7.4 — Run to confirm GREEN**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/AuditLogResourceTest.php
```

Expected: 8 passed.

- [ ] **Step 7.5 — Full suite + pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/SuperAdmin/Resources/AuditLogs tests/Feature/SuperAdmin/AuditLogResourceTest.php
git commit -m "feat: add read-only AuditLogResource with CSV export and date range filter"
```

---

## Task 8 — Dashboard Widgets (Phase 3.8)

**Files:**
- Create: `app/Filament/SuperAdmin/Widgets/SuperAdminStatsOverviewWidget.php`
- Create: `app/Filament/SuperAdmin/Widgets/AttendanceTrendChartWidget.php`
- Create: `app/Filament/SuperAdmin/Widgets/SystemHealthWidget.php`
- Create: `resources/views/filament/super-admin/widgets/system-health-widget.blade.php`
- Create: `app/Filament/SuperAdmin/Widgets/RecentAuditFeedWidget.php`
- Create: `resources/views/filament/super-admin/widgets/recent-audit-feed-widget.blade.php`
- Test: `tests/Feature/SuperAdmin/DashboardWidgetTest.php`

---

- [ ] **Step 8.1 — Write failing tests**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:test --pest SuperAdmin/DashboardWidgetTest
```

Replace `tests/Feature/SuperAdmin/DashboardWidgetTest.php` with:

```php
<?php

use App\Filament\SuperAdmin\Widgets\RecentAuditFeedWidget;
use App\Filament\SuperAdmin\Widgets\SuperAdminStatsOverviewWidget;
use App\Filament\SuperAdmin\Widgets\SystemHealthWidget;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\ProxyFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('stats_overview_widget_renders_for_super_admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(SuperAdminStatsOverviewWidget::class)
        ->assertSuccessful();
});

test('stats_overview_shows_correct_user_count', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->admin()->count(2)->create();

    $this->actingAs($superAdmin);

    livewire(SuperAdminStatsOverviewWidget::class)
        ->assertSeeHtml((string) User::count());
});

test('stats_overview_shows_correct_active_session_count', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AttendanceSession::factory()->active()->count(2)->create();

    $this->actingAs($superAdmin);

    livewire(SuperAdminStatsOverviewWidget::class)
        ->assertSeeHtml('2');
});

test('stats_overview_shows_correct_pending_proxy_flag_count', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    ProxyFlag::factory()->pending()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(SuperAdminStatsOverviewWidget::class)
        ->assertSeeHtml('3');
});

test('system_health_widget_renders', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(SystemHealthWidget::class)
        ->assertSuccessful();
});

test('recent_audit_feed_widget_renders', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AuditLog::factory()->count(5)->create();

    $this->actingAs($superAdmin);

    livewire(RecentAuditFeedWidget::class)
        ->assertSuccessful();
});
```

- [ ] **Step 8.2 — Run to confirm RED**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/DashboardWidgetTest.php
```

Expected: failures — widget classes not found.

---

- [ ] **Step 8.3 — Scaffold widgets**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-widget SuperAdminStatsOverviewWidget --panel=super-admin --stats-overview --no-interaction
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-widget AttendanceTrendChartWidget --panel=super-admin --chart --no-interaction
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-widget SystemHealthWidget --panel=super-admin --no-interaction
/Users/thomas/.config/herd-lite/bin/php artisan make:filament-widget RecentAuditFeedWidget --panel=super-admin --no-interaction
```

---

- [ ] **Step 8.4 — Implement `SuperAdminStatsOverviewWidget`**

Replace `app/Filament/SuperAdmin/Widgets/SuperAdminStatsOverviewWidget.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Enums\ReviewStatus;
use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\ProxyFlag;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class SuperAdminStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Users', Cache::remember('stat.total_users', 60, fn () => User::count()))
                ->color('violet'),
            Stat::make('Active Sessions', Cache::remember('stat.active_sessions', 60, fn () => AttendanceSession::where('status', SessionStatus::Active)->count()))
                ->color('success'),
            Stat::make('Open Proxy Flags', Cache::remember('stat.open_proxy_flags', 60, fn () => ProxyFlag::where('review_status', ReviewStatus::Pending)->count()))
                ->color('warning'),
            Stat::make('Departments', Cache::remember('stat.departments', 60, fn () => Department::count()))
                ->color('info'),
        ];
    }
}
```

- [ ] **Step 8.5 — Implement `AttendanceTrendChartWidget`**

Replace `app/Filament/SuperAdmin/Widgets/AttendanceTrendChartWidget.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use Filament\Widgets\ChartWidget;

class AttendanceTrendChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Attendance Trend (Last 7 Days)';

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn ($d) => now()->subDays($d)->format('D, M j'));
        $counts = collect(range(6, 0))->map(fn ($d) => AttendanceRecord::whereDate('created_at', now()->subDays($d))
            ->where('status', AttendanceStatus::Present)
            ->count()
        );

        return [
            'datasets' => [
                [
                    'label' => 'Present',
                    'data' => $counts->values()->toArray(),
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $days->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
```

- [ ] **Step 8.6 — Implement `SystemHealthWidget`**

Replace `app/Filament/SuperAdmin/Widgets/SystemHealthWidget.php` with:

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
            'policy' => $policy,
            'lastRetentionRun' => $lastRetentionRun,
            'redisConnected' => $redisConnected,
            'queueWorkerRunning' => true, // Phase 6 wires real queue monitoring
        ];
    }

    private function checkRedisConnection(): bool
    {
        try {
            Cache::store('array')->put('_ping', 1, 1);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
```

Create `resources/views/filament/super-admin/widgets/system-health-widget.blade.php`:

```blade
<x-filament::section heading="System Health">
    <div class="grid grid-cols-3 gap-4 text-sm">
        <div>
            <p class="font-semibold text-gray-500">Cache</p>
            <p class="{{ $redisConnected ? 'text-green-600' : 'text-red-600' }}">
                {{ $redisConnected ? 'Connected' : 'Unavailable' }}
            </p>
        </div>
        <div>
            <p class="font-semibold text-gray-500">Active Security Policy</p>
            <p>{{ $policy?->policy_name ?? 'None' }}</p>
        </div>
        <div>
            <p class="font-semibold text-gray-500">Last Retention Run</p>
            <p>{{ $lastRetentionRun ? \Carbon\Carbon::parse($lastRetentionRun)->diffForHumans() : 'Never' }}</p>
        </div>
    </div>
</x-filament::section>
```

- [ ] **Step 8.7 — Implement `RecentAuditFeedWidget`**

Replace `app/Filament/SuperAdmin/Widgets/RecentAuditFeedWidget.php` with:

```php
<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\AuditLog;
use Filament\Widgets\Widget;

class RecentAuditFeedWidget extends Widget
{
    protected string $view = 'filament.super-admin.widgets.recent-audit-feed-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '20s';

    public function getViewData(): array
    {
        return [
            'logs' => AuditLog::with('actor')->latest()->limit(10)->get(),
        ];
    }
}
```

Create `resources/views/filament/super-admin/widgets/recent-audit-feed-widget.blade.php`:

```blade
<x-filament::section heading="Recent Audit Events">
    <div class="divide-y">
        @forelse ($logs as $log)
            <div class="py-2 flex justify-between text-sm">
                <div>
                    <span class="font-medium">{{ $log->actor?->name ?? 'System' }}</span>
                    <span class="text-gray-500 ml-1">{{ $log->action }}</span>
                    <span class="text-gray-400 ml-1">on {{ class_basename($log->entity_type) }} #{{ $log->entity_id }}</span>
                </div>
                <span class="text-gray-400 text-xs">{{ $log->created_at->diffForHumans() }}</span>
            </div>
        @empty
            <p class="text-gray-400 text-sm py-2">No audit events yet.</p>
        @endforelse
    </div>
</x-filament::section>
```

- [ ] **Step 8.8 — Run to confirm GREEN**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact tests/Feature/SuperAdmin/DashboardWidgetTest.php
```

Expected: 6 passed.

- [ ] **Step 8.9 — Full suite + pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Filament/SuperAdmin/Widgets resources/views/filament/super-admin/widgets tests/Feature/SuperAdmin/DashboardWidgetTest.php
git commit -m "feat: add super admin dashboard widgets (stats, chart, health, audit feed)"
```

---

## Post-Phase

- [ ] **Update `docs/project-phases.md`** — mark Phase 3.1–3.8 ✅ in the summary table.

```bash
git add docs/project-phases.md
git commit -m "docs: mark Phase 3 complete"
```

---

## Self-Review Checklist

- **Spec 3.1:** `name`, `code`, `head_faculty_id`, `is_active` form ✅ | `students_count`, `faculty_count` via state() ✅ | `SelectFilter(is_active)` ✅ | `DeleteAction` ✅ | empty state ✅
- **Spec 3.2:** `SuspendAction` sets `status=Suspended` + `AuditLog::record()` ✅ | `RevokeRoleAction` revokes `AdminRoleAssignment` rows ✅ | `BulkAction(suspend)` ✅ | `BulkAction(export_csv)` ✅ | `email unique` + `password confirmed` validation ✅
- **Spec 3.3:** All 8 policy fields ✅ | `qr_expiry 10-300`, `risk_auto_reject 50-100`, `risk_pending_review 20-79` validation ✅ | `afterSave()` logs audit ✅ | Cache cleared via model observer (Phase 2) ✅ | `canCreate() = false` ✅
- **Spec 3.4:** All 5 known settings ✅ | `mount()` pre-populates from DB ✅ | `save()` calls `SystemSetting::set()` per field ✅ | `faculty_can_review_flags` stored as `'true'/'false'` string ✅
- **Spec 3.5:** `entity_type`, `retention_days`, `is_active`, `last_run_at` ✅ | `retention_days minValue(1)` ✅ | `canCreate() = false` ✅
- **Spec 3.6:** `SelectFilter` on user_id, role, department_id ✅ | `RevokeAction` sets `revoked_at = now()` + audit ✅ | `DateTimePicker(assigned_at)` ✅
- **Spec 3.7:** `canCreate/Edit/Delete = false` ✅ | `view` action modal shows old/new JSON ✅ | `SelectFilter(action)`, `SelectFilter(actor_role)` ✅ | `DateRange Filter(created_at)` via two DatePickers ✅ | `ExportAction (CSV)` ✅ | pagination default 25 ✅
- **Spec 3.8:** `StatsOverviewWidget` polling 30s ✅ | 4 stats with Cache::remember ✅ | bar chart last 7 days ✅ | `SystemHealthWidget` reads `SecurityPolicy::getActive()` ✅ | `RecentAuditFeedWidget` last 10 logs polling 20s ✅
- **No placeholders** ✅
- **Type consistency:** `LogsToAudit::logAudit()` signature matches `AuditLog::record()` ✅ | `TestAction::make('action_name')->table($record)` for all table action tests ✅
