# Phase 4.7 — ProxyFlagResource (Admin) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an Admin Filament resource for reviewing, approving, and rejecting proxy attendance flags, with audit logging and a navigation badge showing pending count.

**Architecture:** Follows the established Admin resource pattern — a main `ProxyFlagResource` class delegates to `ProxyFlagTable` for table config. No create/edit pages; this is a review-only resource with inline record actions (Approve, Reject, View Evidence) and bulk actions. Default sort by severity (critical first) then `created_at` descending, applied in `getEloquentQuery()`.

**Tech Stack:** Filament v4, Pest v3, `App\Models\ProxyFlag`, `App\Enums\ProxySeverity`, `App\Enums\ReviewStatus`, `App\Models\AuditLog`, `App\Concerns\LogsToAudit`

---

## File Map

| File | Action | Purpose |
|---|---|---|
| `app/Filament/Admin/Resources/ProxyFlags/ProxyFlagResource.php` | Create | Resource registration, `getEloquentQuery()`, navigation badge |
| `app/Filament/Admin/Resources/ProxyFlags/Pages/ListProxyFlags.php` | Create | List page (only page — no create/edit) |
| `app/Filament/Admin/Resources/ProxyFlags/Tables/ProxyFlagTable.php` | Create | Columns, filters, record actions, bulk actions |
| `tests/Feature/Admin/ProxyFlagResourceTest.php` | Create | All 9 acceptance tests |

---

## Task 1: ProxyFlagTable — columns, filters, and actions

**Files:**
- Create: `app/Filament/Admin/Resources/ProxyFlags/Tables/ProxyFlagTable.php`

- [ ] **Step 1: Create the table class**

```php
<?php

namespace App\Filament\Admin\Resources\ProxyFlags\Tables;

use App\Enums\ProxySeverity;
use App\Enums\ReviewStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\ProxyFlag;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class ProxyFlagTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendanceRecord.student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('attendanceRecord.session.course.code')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('severity')
                    ->badge()
                    ->sortable(),
                TextColumn::make('reason_code')
                    ->label('Reason')
                    ->sortable(),
                TextColumn::make('risk_score')
                    ->label('Risk Score')
                    ->badge()
                    ->color(fn (ProxyFlag $record): string => match (true) {
                        $record->risk_score >= 80 => 'danger',
                        $record->risk_score >= 50 => 'warning',
                        default => 'success',
                    })
                    ->sortable(),
                TextColumn::make('review_status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Flagged At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options(ProxySeverity::class),
                SelectFilter::make('review_status')
                    ->options(ReviewStatus::class),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']))
                    ),
                Filter::make('course')
                    ->form([
                        Select::make('course_id')
                            ->label('Course')
                            ->options(fn () => Course::orderBy('code')->pluck('code', 'id'))
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['course_id'], fn ($q) => $q->whereHas(
                            'attendanceRecord.session',
                            fn ($q) => $q->where('course_id', $data['course_id'])
                        ))
                    ),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ProxyFlag $record): bool => $record->review_status === ReviewStatus::Pending)
                    ->schema([
                        Textarea::make('reviewer_notes')
                            ->label('Notes (optional)')
                            ->rows(3),
                    ])
                    ->action(function (ProxyFlag $record, array $data): void {
                        $old = $record->only(['review_status', 'reviewer_notes']);
                        $record->update([
                            'review_status' => ReviewStatus::Approved,
                            'reviewer_id' => auth()->id(),
                            'reviewer_notes' => $data['reviewer_notes'] ?? null,
                            'reviewed_at' => now(),
                        ]);
                        AuditLog::record('proxy_flag.approved', $record, $old, [
                            'review_status' => ReviewStatus::Approved->value,
                            'reviewer_notes' => $data['reviewer_notes'] ?? null,
                        ]);
                        Notification::make()->title('Proxy flag approved')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (ProxyFlag $record): bool => $record->review_status === ReviewStatus::Pending)
                    ->schema([
                        Textarea::make('reviewer_notes')
                            ->label('Reason (required)')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (ProxyFlag $record, array $data): void {
                        $old = $record->only(['review_status', 'reviewer_notes']);
                        $record->update([
                            'review_status' => ReviewStatus::Rejected,
                            'reviewer_id' => auth()->id(),
                            'reviewer_notes' => $data['reviewer_notes'],
                            'reviewed_at' => now(),
                        ]);
                        AuditLog::record('proxy_flag.rejected', $record, $old, [
                            'review_status' => ReviewStatus::Rejected->value,
                            'reviewer_notes' => $data['reviewer_notes'],
                        ]);
                        Notification::make()->title('Proxy flag rejected')->success()->send();
                    }),
                Action::make('view_evidence')
                    ->label('Evidence')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->modalContent(fn (ProxyFlag $record) => new HtmlString(
                        self::renderEvidenceHtml($record)
                    ))
                    ->modalSubmitAction(false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_approve')
                        ->label('Approve Selected')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->schema([
                            Textarea::make('reviewer_notes')
                                ->label('Notes (optional)')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (ProxyFlag $flag) => $flag->update([
                                'review_status' => ReviewStatus::Approved,
                                'reviewer_id' => auth()->id(),
                                'reviewer_notes' => $data['reviewer_notes'] ?? null,
                                'reviewed_at' => now(),
                            ]));
                            Notification::make()->title('Proxy flags approved')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('bulk_reject')
                        ->label('Reject Selected')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('danger')
                        ->schema([
                            Textarea::make('reviewer_notes')
                                ->label('Reason (required)')
                                ->rows(3)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (ProxyFlag $flag) => $flag->update([
                                'review_status' => ReviewStatus::Rejected,
                                'reviewer_id' => auth()->id(),
                                'reviewer_notes' => $data['reviewer_notes'],
                                'reviewed_at' => now(),
                            ]));
                            Notification::make()->title('Proxy flags rejected')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No proxy flags found')
            ->emptyStateDescription('Proxy flags are generated automatically when suspicious attendance scans are detected.');
    }

    private static function renderEvidenceHtml(ProxyFlag $flag): string
    {
        $evidence = $flag->evidence_json ?? [];
        $html = '<div class="space-y-3 text-sm">';

        if (isset($evidence['latitude'], $evidence['longitude'])) {
            $html .= '<p class="font-semibold">GPS Location</p>';
            $html .= '<p>Lat: '.e($evidence['latitude']).', Lng: '.e($evidence['longitude']).'</p>';
        }

        if (isset($evidence['device'])) {
            $html .= '<p class="font-semibold mt-2">Device Info</p>';
            $html .= '<pre class="text-xs bg-gray-50 p-2 rounded">'.e(json_encode($evidence['device'], JSON_PRETTY_PRINT)).'</pre>';
        }

        $riskKeys = ['risk_score', 'clock_skew', 'distance_m', 'device_match'];
        $riskData = array_intersect_key($evidence, array_flip($riskKeys));

        if (! empty($riskData)) {
            $html .= '<p class="font-semibold mt-2">Risk Breakdown</p>';
            $html .= '<pre class="text-xs bg-gray-50 p-2 rounded">'.e(json_encode($riskData, JSON_PRETTY_PRINT)).'</pre>';
        }

        if (empty($evidence)) {
            $html .= '<p class="text-gray-500">No evidence data available.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
```

- [ ] **Step 2: Verify the file exists**

Run: `/Users/thomas/.config/herd-lite/bin/php artisan list 2>/dev/null | head -5`
Expected: No parse errors (artisan still boots).

---

## Task 2: ProxyFlagResource and ListProxyFlags page

**Files:**
- Create: `app/Filament/Admin/Resources/ProxyFlags/ProxyFlagResource.php`
- Create: `app/Filament/Admin/Resources/ProxyFlags/Pages/ListProxyFlags.php`

- [ ] **Step 1: Create the resource**

```php
<?php

namespace App\Filament\Admin\Resources\ProxyFlags;

use App\Filament\Admin\Resources\ProxyFlags\Pages\ListProxyFlags;
use App\Filament\Admin\Resources\ProxyFlags\Tables\ProxyFlagTable;
use App\Models\ProxyFlag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProxyFlagResource extends Resource
{
    protected static ?string $model = ProxyFlag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|\UnitEnum|null $navigationGroup = 'Attendance';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::pending()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'attendanceRecord.student.user',
                'attendanceRecord.session.course',
            ])
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->orderBy('created_at', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return ProxyFlagTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProxyFlags::route('/'),
        ];
    }
}
```

- [ ] **Step 2: Create the list page**

```php
<?php

namespace App\Filament\Admin\Resources\ProxyFlags\Pages;

use App\Filament\Admin\Resources\ProxyFlags\ProxyFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListProxyFlags extends ListRecords
{
    protected static string $resource = ProxyFlagResource::class;
}
```

- [ ] **Step 3: Verify Filament can discover the resource**

Run: `/Users/thomas/.config/herd-lite/bin/php artisan filament:check-page-access admin 2>/dev/null || /Users/thomas/.config/herd-lite/bin/php artisan route:list --path=admin 2>&1 | grep -i proxy | head -5`
Expected: Route(s) containing `proxy` are listed, or no error from artisan.

---

## Task 3: Write and run the tests

**Files:**
- Create: `tests/Feature/Admin/ProxyFlagResourceTest.php`
- Test: `/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=ProxyFlagResource`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Enums\ReviewStatus;
use App\Filament\Admin\Resources\ProxyFlags\Pages\ListProxyFlags;
use App\Filament\Admin\Resources\ProxyFlags\ProxyFlagResource;
use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\ProxyFlag;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForProxyFlags(): User
{
    $dept = Department::factory()->create();
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $dept->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_list_proxy_flags', function () {
    $admin = adminForProxyFlags();
    $flags = ProxyFlag::factory()->count(3)->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->assertCanSeeTableRecords($flags);
});

test('admin_can_approve_proxy_flag_with_optional_note', function () {
    $admin = adminForProxyFlags();
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('approve')->table($flag), [
            'reviewer_notes' => 'Looks legitimate',
        ])
        ->assertNotified();

    assertDatabaseHas(ProxyFlag::class, [
        'id' => $flag->id,
        'review_status' => ReviewStatus::Approved->value,
        'reviewer_notes' => 'Looks legitimate',
        'reviewer_id' => $admin->id,
    ]);
});

test('admin_can_approve_proxy_flag_without_note', function () {
    $admin = adminForProxyFlags();
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('approve')->table($flag), [
            'reviewer_notes' => null,
        ])
        ->assertNotified();

    assertDatabaseHas(ProxyFlag::class, [
        'id' => $flag->id,
        'review_status' => ReviewStatus::Approved->value,
    ]);
});

test('admin_can_reject_proxy_flag_with_required_note', function () {
    $admin = adminForProxyFlags();
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('reject')->table($flag), [
            'reviewer_notes' => 'Device fingerprint did not match any registered device.',
        ])
        ->assertNotified();

    assertDatabaseHas(ProxyFlag::class, [
        'id' => $flag->id,
        'review_status' => ReviewStatus::Rejected->value,
        'reviewer_notes' => 'Device fingerprint did not match any registered device.',
        'reviewer_id' => $admin->id,
    ]);
});

test('reject_without_notes_fails_validation', function () {
    $admin = adminForProxyFlags();
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('reject')->table($flag), [
            'reviewer_notes' => null,
        ])
        ->assertHasActionErrors(['reviewer_notes' => 'required']);
});

test('approve_action_writes_audit_log', function () {
    $admin = adminForProxyFlags();
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('approve')->table($flag), [
            'reviewer_notes' => 'Approved.',
        ]);

    assertDatabaseHas(AuditLog::class, [
        'action' => 'proxy_flag.approved',
        'entity_type' => ProxyFlag::class,
        'entity_id' => $flag->id,
        'actor_id' => $admin->id,
    ]);
});

test('reject_action_writes_audit_log', function () {
    $admin = adminForProxyFlags();
    $flag = ProxyFlag::factory()->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callAction(TestAction::make('reject')->table($flag), [
            'reviewer_notes' => 'Suspicious activity confirmed.',
        ]);

    assertDatabaseHas(AuditLog::class, [
        'action' => 'proxy_flag.rejected',
        'entity_type' => ProxyFlag::class,
        'entity_id' => $flag->id,
        'actor_id' => $admin->id,
    ]);
});

test('bulk_approve_updates_all_selected_flags', function () {
    $admin = adminForProxyFlags();
    $flags = ProxyFlag::factory()->count(3)->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callTableBulkAction('bulk_approve', $flags, [
            'reviewer_notes' => null,
        ])
        ->assertNotified();

    foreach ($flags as $flag) {
        assertDatabaseHas(ProxyFlag::class, [
            'id' => $flag->id,
            'review_status' => ReviewStatus::Approved->value,
        ]);
    }
});

test('bulk_reject_requires_reason', function () {
    $admin = adminForProxyFlags();
    $flags = ProxyFlag::factory()->count(2)->pending()->create();

    $this->actingAs($admin);

    livewire(ListProxyFlags::class)
        ->callTableBulkAction('bulk_reject', $flags, [])
        ->assertHasErrors(['reviewer_notes' => 'required']);
});

test('navigation_badge_shows_pending_count', function () {
    $admin = adminForProxyFlags();
    $this->actingAs($admin);

    ProxyFlag::factory()->count(3)->pending()->create();
    ProxyFlag::factory()->count(2)->create(['review_status' => ReviewStatus::Approved]);

    expect(ProxyFlagResource::getNavigationBadge())->toBe('3');
});
```

- [ ] **Step 2: Run the failing tests first**

Run: `/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=ProxyFlagResource 2>&1`
Expected: Tests FAIL because files don't exist yet (if running before Tasks 1 & 2) OR they PASS if tasks were completed in order. Confirm no parse errors.

- [ ] **Step 3: Run all tests to check for regressions**

Run: `/Users/thomas/.config/herd-lite/bin/php artisan test --compact 2>&1 | tail -20`
Expected: All previously passing tests still pass. No new failures outside `ProxyFlagResource`.

---

## Task 4: Format and commit

**Files:**
- Modify: All new PHP files (pint formatting)

- [ ] **Step 1: Run Pint on all new files**

Run: `vendor/bin/pint --dirty --format agent 2>&1`
Expected: Lists files formatted (or "No files were modified" if already clean).

- [ ] **Step 2: Run the full test suite**

Run: `/Users/thomas/.config/herd-lite/bin/php artisan test --compact 2>&1 | tail -20`
Expected: All tests pass. No failures.

- [ ] **Step 3: Commit**

```bash
git add \
  app/Filament/Admin/Resources/ProxyFlags/ \
  tests/Feature/Admin/ProxyFlagResourceTest.php
git commit -m "$(cat <<'EOF'
feat: add Admin ProxyFlagResource (Phase 4.7)

Approve/reject proxy attendance flags with audit logging,
evidence modal, bulk actions, and pending-count nav badge.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review

### Spec coverage check

| Requirement | Covered? | Task |
|---|---|---|
| Table: student name, course code | ✅ | Task 1 |
| Table: severity badge | ✅ | Task 1 |
| Table: reason_code | ✅ | Task 1 |
| Table: risk_score coloured (red ≥80, amber ≥50) | ✅ | Task 1 |
| Table: review_status badge | ✅ | Task 1 |
| Table: created_at | ✅ | Task 1 |
| Default sort: severity desc, created_at desc | ✅ | Task 2 (`getEloquentQuery`) |
| ApproveAction (optional notes + AuditLog) | ✅ | Task 1 |
| RejectAction (required notes + AuditLog) | ✅ | Task 1 |
| ViewEvidenceAction (modal: GPS, device, risk) | ✅ | Task 1 |
| BulkApproveAction (optional note) | ✅ | Task 1 |
| BulkRejectAction (required reason) | ✅ | Task 1 |
| Filter: severity | ✅ | Task 1 |
| Filter: review_status | ✅ | Task 1 |
| DateRangeFilter: created_at | ✅ | Task 1 |
| Filter: course via relationship | ✅ | Task 1 |
| Navigation badge: pending count | ✅ | Task 2 |
| `test_admin_can_list_proxy_flags` | ✅ | Task 3 |
| `test_admin_can_approve_proxy_flag_with_optional_note` | ✅ | Task 3 |
| `test_admin_can_reject_proxy_flag_with_required_note` | ✅ | Task 3 |
| `test_reject_without_notes_fails_validation` | ✅ | Task 3 |
| `test_approve_action_writes_audit_log` | ✅ | Task 3 |
| `test_reject_action_writes_audit_log` | ✅ | Task 3 |
| `test_bulk_approve_updates_all_selected_flags` | ✅ | Task 3 |
| `test_bulk_reject_requires_reason` | ✅ | Task 3 |
| `test_navigation_badge_shows_pending_count` | ✅ | Task 3 |

### Notes for executor

- `AttendanceRecord` uses the relationship name `session()` (FK: `attendance_session_id`). The dot-notation path `attendanceRecord.session.course.code` is correct — do not use `attendanceRecord.attendanceSession.course.code`.
- The `pending()` scope on `ProxyFlag` returns `where('review_status', ReviewStatus::Pending)`.
- `FIELD(severity, 'critical', 'high', 'medium', 'low')` returns 1 for critical, 4 for low — lower number sorts first — which gives critical→high→medium→low ordering.
- The `bulk_reject_requires_reason` test uses `assertHasErrors` (Livewire level) rather than `assertHasActionErrors` because BulkAction validation errors may surface differently than record action errors. If one assertion fails, try the other.
- If `->schema()` on `BulkAction` is not supported in this version of Filament v4, replace it with `->form([...])` — same field definitions, same validation rules.
