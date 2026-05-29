# Phase 5.4 — Faculty ProxyFlagResource Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only ProxyFlagResource to the Faculty panel that shows flags from the faculty member's own sessions, with optional Allow/Deny review actions gated on a system setting.

**Architecture:** Three files mirror the Admin ProxyFlagResource pattern — Resource class handles scoped query and navigation, a Table class holds columns and actions, and a ListPage wires them together. The key difference is the `whereHas` scope limiting results to the authenticated faculty's sessions, and Allow/Deny visibility driven by `SystemSetting::get('faculty_can_review_flags')`.

**Tech Stack:** Filament v4, Laravel 12, Pest v3, `SystemSetting` model (with cache), `AuditLog::record()`.

---

## File Map

| Action | Path |
|---|---|
| Create | `app/Filament/Faculty/Resources/ProxyFlags/ProxyFlagResource.php` |
| Create | `app/Filament/Faculty/Resources/ProxyFlags/Pages/ListProxyFlags.php` |
| Create | `app/Filament/Faculty/Resources/ProxyFlags/Tables/ProxyFlagTable.php` |
| Create | `tests/Feature/Faculty/ProxyFlagResourceTest.php` |

---

## Task 1: Write the test file (all 4 tests fail)

**Files:**
- Create: `tests/Feature/Faculty/ProxyFlagResourceTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Filament\Faculty\Resources\ProxyFlags\Pages\ListProxyFlags;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\ProxyFlag;
use App\Models\SystemSetting;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('faculty'));
});

function proxyFlagForFaculty(Faculty $faculty): ProxyFlag
{
    $session = AttendanceSession::factory()->active()->create(['faculty_id' => $faculty->id]);
    $record = AttendanceRecord::factory()->pendingReview()->create(['attendance_session_id' => $session->id]);

    return ProxyFlag::factory()->pending()->create(['attendance_record_id' => $record->id]);
}

test('faculty_can_list_flags_for_own_sessions', function () {
    $faculty = Faculty::factory()->create();
    $flag = proxyFlagForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListProxyFlags::class)
        ->assertCanSeeTableRecords([$flag]);
});

test('faculty_cannot_see_flags_from_other_sessions', function () {
    $faculty = Faculty::factory()->create();
    $otherFaculty = Faculty::factory()->create();

    $myFlag = proxyFlagForFaculty($faculty);
    $otherFlag = proxyFlagForFaculty($otherFaculty);

    $this->actingAs($faculty->user);

    livewire(ListProxyFlags::class)
        ->assertCanSeeTableRecords([$myFlag])
        ->assertCanNotSeeTableRecords([$otherFlag]);
});

test('allow_deny_actions_visible_when_policy_permits', function () {
    SystemSetting::set('faculty_can_review_flags', 'true');

    $faculty = Faculty::factory()->create();
    $flag = proxyFlagForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListProxyFlags::class)
        ->assertTableActionVisible('allow', $flag)
        ->assertTableActionVisible('deny', $flag);
});

test('allow_deny_actions_hidden_when_policy_disallows', function () {
    SystemSetting::set('faculty_can_review_flags', 'false');

    $faculty = Faculty::factory()->create();
    $flag = proxyFlagForFaculty($faculty);

    $this->actingAs($faculty->user);

    livewire(ListProxyFlags::class)
        ->assertTableActionHidden('allow', $flag)
        ->assertTableActionHidden('deny', $flag);
});
```

- [ ] **Step 2: Run tests to confirm all 4 fail**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=ProxyFlagResourceTest tests/Feature/Faculty/
```

Expected: 4 failures — `ListProxyFlags` class not found.

---

## Task 2: Create the Resource and List Page

**Files:**
- Create: `app/Filament/Faculty/Resources/ProxyFlags/ProxyFlagResource.php`
- Create: `app/Filament/Faculty/Resources/ProxyFlags/Pages/ListProxyFlags.php`

- [ ] **Step 1: Create `ListProxyFlags.php`**

```php
<?php

namespace App\Filament\Faculty\Resources\ProxyFlags\Pages;

use App\Filament\Faculty\Resources\ProxyFlags\ProxyFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListProxyFlags extends ListRecords
{
    protected static string $resource = ProxyFlagResource::class;
}
```

- [ ] **Step 2: Create `ProxyFlagResource.php`**

```php
<?php

namespace App\Filament\Faculty\Resources\ProxyFlags;

use App\Filament\Faculty\Resources\ProxyFlags\Pages\ListProxyFlags;
use App\Filament\Faculty\Resources\ProxyFlags\Tables\ProxyFlagTable;
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

    protected static string|\UnitEnum|null $navigationGroup = 'My Sessions';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $facultyId = auth()->user()?->faculty?->id;

        return parent::getEloquentQuery()
            ->with([
                'attendanceRecord.student.user',
                'attendanceRecord.session.course',
            ])
            ->when(
                $facultyId,
                fn (Builder $q) => $q->whereHas(
                    'attendanceRecord.session',
                    fn (Builder $q) => $q->where('faculty_id', $facultyId)
                )
            );
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

- [ ] **Step 3: Run tests — expect fail with `ProxyFlagTable` class not found**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=ProxyFlagResourceTest tests/Feature/Faculty/
```

Expected: errors referencing `ProxyFlagTable` not found — confirms wiring is correct.

---

## Task 3: Create the Table class

**Files:**
- Create: `app/Filament/Faculty/Resources/ProxyFlags/Tables/ProxyFlagTable.php`

- [ ] **Step 1: Create `ProxyFlagTable.php`**

```php
<?php

namespace App\Filament\Faculty\Resources\ProxyFlags\Tables;

use App\Enums\ReviewStatus;
use App\Models\AuditLog;
use App\Models\ProxyFlag;
use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
            ])
            ->recordActions([
                Action::make('allow')
                    ->label('Allow')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ProxyFlag $record): bool => SystemSetting::get('faculty_can_review_flags') === 'true'
                        && $record->review_status === ReviewStatus::Pending)
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
                        AuditLog::record('proxy_flag.allowed', $record, $old, [
                            'review_status' => ReviewStatus::Approved->value,
                            'reviewer_notes' => $data['reviewer_notes'] ?? null,
                        ]);
                        Notification::make()->title('Proxy flag allowed')->success()->send();
                    }),

                Action::make('deny')
                    ->label('Deny')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (ProxyFlag $record): bool => SystemSetting::get('faculty_can_review_flags') === 'true'
                        && $record->review_status === ReviewStatus::Pending)
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
                        AuditLog::record('proxy_flag.denied', $record, $old, [
                            'review_status' => ReviewStatus::Rejected->value,
                            'reviewer_notes' => $data['reviewer_notes'],
                        ]);
                        Notification::make()->title('Proxy flag denied')->success()->send();
                    }),

                Action::make('view_evidence')
                    ->label('Evidence')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->modalContent(fn (ProxyFlag $record) => new HtmlString(
                        self::renderEvidenceHtml($record)
                    ))
                    ->modalSubmitAction(false),
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

- [ ] **Step 2: Run all 4 tests — expect all pass**

```bash
/Users/thomas/.config/herd-lite/bin/php artisan test --compact --filter=ProxyFlagResourceTest tests/Feature/Faculty/
```

Expected: 4 passing.

---

## Task 4: Pint and commit

- [ ] **Step 1: Run Pint to fix formatting**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Commit**

```bash
git add \
  app/Filament/Faculty/Resources/ProxyFlags/ProxyFlagResource.php \
  app/Filament/Faculty/Resources/ProxyFlags/Pages/ListProxyFlags.php \
  app/Filament/Faculty/Resources/ProxyFlags/Tables/ProxyFlagTable.php \
  tests/Feature/Faculty/ProxyFlagResourceTest.php

git commit -m "feat: add Faculty ProxyFlagResource with Allow/Deny actions (Phase 5.4)"
```
