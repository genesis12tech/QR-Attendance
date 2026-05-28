# Phase 2: Core Services — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the four core service layers — QR challenge generation/validation, audit logging trait, caching wrappers, and real-time broadcast event.

**Architecture:** Each sub-phase is independent and builds on Phase 1 models. Phase 2.1 (QRChallengeService) and 2.4 (broadcast) are the most complex; 2.2 (LogsToAudit) and 2.3 (caching) are small wrappers around existing code. Phase 2.3 caching is wired into QRChallengeService at the end of Task 3.

**Tech Stack:** PHP 8.4 · Laravel 12 · Pest 3 · `simplesoftwareio/simple-qrcode` (Imagick PNG) · `Cache::remember` / `Cache::forget` (array driver in tests, redis in prod) · `Broadcast` (null in tests, reverb in prod)

---

## File Map

| Action | Path | Purpose |
|---|---|---|
| Create | `app/Services/QRChallengeService.php` | QR payload generation + validation |
| Create | `config/services.php` *(modify)* | Add `qr_secret` key |
| Modify | `.env.example` | Add `QR_SECRET=` |
| Modify | `phpunit.xml` | Add `QR_SECRET` test value |
| Create | `tests/Unit/Services/QRChallengeServiceTest.php` | 7 unit tests |
| Create | `app/Concerns/LogsToAudit.php` | Trait wrapping `AuditLog::record()` |
| Create | `tests/Feature/AuditLogTest.php` | 5 feature tests for AuditLog behaviour |
| Modify | `app/Models/SecurityPolicy.php` | Add `getActive()` cached static helper + `saved` observer to bust cache |
| Modify | `app/Models/SystemSetting.php` | Wrap `get()` with `Cache::remember`, `set()` with `Cache::forget` |
| Create | `tests/Unit/Services/CachingTest.php` | 4 caching tests |
| Modify | `app/Services/QRChallengeService.php` | Use `SecurityPolicy::getActive()` instead of `->active()->first()` |
| Create | `app/Events/AttendanceMarked.php` | `ShouldBroadcast` event |
| Modify | `routes/channels.php` | `session.{uuid}` private-channel authorisation |
| Create | `tests/Feature/Broadcasting/AttendanceMarkedTest.php` | 4 broadcast tests |

---

## Task 1 — QRChallengeService (Phase 2.1)

**Files:**
- Create: `app/Services/QRChallengeService.php`
- Modify: `config/services.php` (add `qr_secret`)
- Modify: `.env.example` (add `QR_SECRET=`)
- Modify: `phpunit.xml` (add `QR_SECRET` env)
- Create: `tests/Unit/Services/QRChallengeServiceTest.php`

---

- [ ] **Step 1.1 — Add `QR_SECRET` to config and env files**

Append to `config/services.php` (inside the return array, after existing entries):
```php
'qr_secret' => env('QR_SECRET', 'changeme'),
```

Append to `.env.example`:
```
QR_SECRET=
```

Add inside `<php>` in `phpunit.xml`:
```xml
<env name="QR_SECRET" value="test-qr-secret-key"/>
```

Also add to your local `.env`:
```
QR_SECRET=local-dev-qr-secret
```

---

- [ ] **Step 1.2 — Write all failing tests**

Run: `php artisan make:test --pest --unit QRChallengeServiceTest`
Then **replace** the generated file at `tests/Unit/Services/QRChallengeServiceTest.php` with:

```php
<?php

use App\Models\AttendanceSession;
use App\Models\SecurityPolicy;
use App\Services\QRChallengeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('generate_for_session_returns_base64_png_string', function () {
    $session = AttendanceSession::factory()->create();
    SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30, 'is_active' => true]);

    $result = app(QRChallengeService::class)->generateForSession($session);

    expect($result)->toBeString()->not->toBeEmpty();
    $decoded = base64_decode($result, strict: true);
    expect($decoded)->not->toBeFalse();
    expect(substr($decoded, 0, 4))->toBe("\x89PNG");
});

test('generate_stores_payload_in_redis_with_correct_ttl', function () {
    Cache::spy();
    $session = AttendanceSession::factory()->create();
    SecurityPolicy::factory()->create(['qr_expiry_seconds' => 45, 'is_active' => true]);

    app(QRChallengeService::class)->generateForSession($session);

    Cache::shouldHaveReceived('put')
        ->withArgs(fn ($key, $value, $ttl) =>
            str_starts_with($key, "qr:{$session->uuid}:") && $ttl === 45
        );
});

test('validate_scan_returns_true_for_valid_fresh_payload', function () {
    $session = AttendanceSession::factory()->create();
    SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30, 'clock_skew_seconds' => 5, 'is_active' => true]);

    $payload = makeQrPayload($session->uuid, now()->timestamp);

    expect(app(QRChallengeService::class)->validateScan($payload, $session))->toBeTrue();
});

test('validate_scan_returns_false_for_expired_payload', function () {
    $session = AttendanceSession::factory()->create();
    SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30, 'clock_skew_seconds' => 5, 'is_active' => true]);

    // issued_at is 40 seconds ago — outside qr_expiry + clock_skew (35s total window)
    $payload = makeQrPayload($session->uuid, now()->subSeconds(40)->timestamp);

    expect(app(QRChallengeService::class)->validateScan($payload, $session))->toBeFalse();
});

test('validate_scan_returns_false_for_tampered_hmac', function () {
    $session = AttendanceSession::factory()->create();
    SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30, 'clock_skew_seconds' => 5, 'is_active' => true]);

    $data = json_decode(base64_decode(makeQrPayload($session->uuid, now()->timestamp)), true);
    $data['hmac'] = 'tampered-value';
    $payload = base64_encode(json_encode($data));

    expect(app(QRChallengeService::class)->validateScan($payload, $session))->toBeFalse();
});

test('validate_scan_returns_false_for_wrong_session', function () {
    $session = AttendanceSession::factory()->create();
    $otherSession = AttendanceSession::factory()->create();
    SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30, 'clock_skew_seconds' => 5, 'is_active' => true]);

    // Payload is signed for $otherSession but validated against $session
    $payload = makeQrPayload($otherSession->uuid, now()->timestamp);

    expect(app(QRChallengeService::class)->validateScan($payload, $session))->toBeFalse();
});

test('validate_scan_allows_clock_skew_within_policy_tolerance', function () {
    $session = AttendanceSession::factory()->create();
    // Total window = 30 + 10 = 40 seconds
    SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30, 'clock_skew_seconds' => 10, 'is_active' => true]);

    // Payload from 35s ago — outside qr_expiry (30) but inside total window (40)
    $payload = makeQrPayload($session->uuid, now()->subSeconds(35)->timestamp);

    expect(app(QRChallengeService::class)->validateScan($payload, $session))->toBeTrue();
});

// ── Helper ────────────────────────────────────────────────────────────────────

function makeQrPayload(string $sessionUuid, int $issuedAt): string
{
    $inner = ['session_uuid' => $sessionUuid, 'nonce' => 'test-nonce', 'issued_at' => $issuedAt];
    $hmac = hash_hmac('sha256', json_encode($inner), config('services.qr_secret'));

    return base64_encode(json_encode(array_merge($inner, ['hmac' => $hmac])));
}
```

- [ ] **Step 1.3 — Run tests to confirm RED**

```bash
php artisan test --compact tests/Unit/Services/QRChallengeServiceTest.php
```

Expected: 7 failures — `QRChallengeService` class not found.

---

- [ ] **Step 1.4 — Create `QRChallengeService`**

Create `app/Services/QRChallengeService.php`:

```php
<?php

namespace App\Services;

use App\Models\AttendanceSession;
use App\Models\SecurityPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRChallengeService
{
    public function generateForSession(AttendanceSession $session): string
    {
        $policy = SecurityPolicy::active()->first();
        $nonce = Str::uuid()->toString();
        $issuedAt = now()->timestamp;

        $inner = ['session_uuid' => $session->uuid, 'nonce' => $nonce, 'issued_at' => $issuedAt];
        $hmac = hash_hmac('sha256', json_encode($inner), config('services.qr_secret'));
        $encoded = base64_encode(json_encode(array_merge($inner, ['hmac' => $hmac])));

        Cache::put("qr:{$session->uuid}:{$nonce}", $encoded, $policy->qr_expiry_seconds);

        return base64_encode((string) QrCode::format('png')->generate($encoded));
    }

    public function validateScan(string $payload, AttendanceSession $session): bool
    {
        $data = json_decode(base64_decode($payload), true);

        if (! is_array($data) || ! array_key_exists('session_uuid', $data)
            || ! array_key_exists('nonce', $data)
            || ! array_key_exists('issued_at', $data)
            || ! array_key_exists('hmac', $data)) {
            return false;
        }

        if ($data['session_uuid'] !== $session->uuid) {
            return false;
        }

        $hmac = $data['hmac'];
        unset($data['hmac']);
        $expected = hash_hmac('sha256', json_encode($data), config('services.qr_secret'));

        if (! hash_equals($expected, $hmac)) {
            return false;
        }

        $policy = SecurityPolicy::active()->first();
        $window = $policy->qr_expiry_seconds + $policy->clock_skew_seconds;

        return now()->timestamp - $data['issued_at'] <= $window;
    }
}
```

- [ ] **Step 1.5 — Run tests to confirm GREEN**

```bash
php artisan test --compact tests/Unit/Services/QRChallengeServiceTest.php
```

Expected: 7 passed.

- [ ] **Step 1.6 — Run full suite to check for regressions**

```bash
php artisan test --compact
```

Expected: all pass.

- [ ] **Step 1.7 — Pint + commit**

```bash
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Services/QRChallengeService.php config/services.php .env.example phpunit.xml tests/Unit/Services/QRChallengeServiceTest.php
git commit -m "feat: add QRChallengeService with HMAC payload generation and validation"
```

---

## Task 2 — LogsToAudit Trait + AuditLog Feature Tests (Phase 2.2)

**Files:**
- Create: `app/Concerns/LogsToAudit.php`
- Create: `tests/Feature/AuditLogTest.php`

> Note: `AuditLog::record()` already exists on the model (implemented in Phase 1.5). This task only adds the trait and the feature-level tests.

---

- [ ] **Step 2.1 — Write failing tests**

Run: `php artisan make:test --pest AuditLogTest`
Then **replace** `tests/Feature/AuditLogTest.php` with:

```php
<?php

use App\Concerns\LogsToAudit;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('audit_log_record_persists_to_database', function () {
    $user = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create();

    AuditLog::record('dept.created', $dept, [], ['name' => $dept->name], $user);

    expect(AuditLog::count())->toBe(1);
});

test('audit_log_captures_actor_id_and_role', function () {
    $user = User::factory()->admin()->create();
    $dept = Department::factory()->create();

    AuditLog::record('dept.updated', $dept, [], [], $user);

    $log = AuditLog::first();
    expect($log->actor_id)->toBe($user->id);
    expect($log->actor_role)->toBe('admin');
});

test('audit_log_captures_ip_address', function () {
    $user = User::factory()->create();
    $dept = Department::factory()->create();

    $this->actingAs($user);
    AuditLog::record('dept.viewed', $dept, [], [], $user);

    expect(AuditLog::first()->ip_address)->not->toBeNull();
});

test('audit_log_stores_old_and_new_values_as_json', function () {
    $user = User::factory()->create();
    $dept = Department::factory()->create();

    AuditLog::record('dept.updated', $dept, ['name' => 'Old'], ['name' => 'New'], $user);

    $log = AuditLog::first();
    expect($log->old_values)->toBe(['name' => 'Old']);
    expect($log->new_values)->toBe(['name' => 'New']);
});

test('audit_log_works_with_null_actor_for_system_actions', function () {
    $dept = Department::factory()->create();

    AuditLog::record('system.cleanup', $dept, [], []);

    $log = AuditLog::first();
    expect($log->actor_id)->toBeNull();
    expect($log->actor_role)->toBeNull();
});

test('logs_to_audit_trait_provides_log_audit_method', function () {
    $user = User::factory()->superAdmin()->create();
    $dept = Department::factory()->create();

    $component = new class {
        use LogsToAudit;
    };

    $this->actingAs($user);
    $component->logAudit('dept.created', $dept, [], ['name' => $dept->name]);

    expect(AuditLog::count())->toBe(1);
    expect(AuditLog::first()->action)->toBe('dept.created');
});
```

- [ ] **Step 2.2 — Run to confirm RED**

```bash
php artisan test --compact tests/Feature/AuditLogTest.php
```

Expected: failures — `LogsToAudit` trait not found.

---

- [ ] **Step 2.3 — Create `LogsToAudit` trait**

Create `app/Concerns/LogsToAudit.php`:

```php
<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait LogsToAudit
{
    public function logAudit(string $action, Model $entity, array $old = [], array $new = []): void
    {
        AuditLog::record($action, $entity, $old, $new);
    }
}
```

- [ ] **Step 2.4 — Run to confirm GREEN**

```bash
php artisan test --compact tests/Feature/AuditLogTest.php
```

Expected: 6 passed.

- [ ] **Step 2.5 — Run full suite + pint + commit**

```bash
php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Concerns/LogsToAudit.php tests/Feature/AuditLogTest.php
git commit -m "feat: add LogsToAudit trait and AuditLog feature tests"
```

---

## Task 3 — SecurityPolicy & SystemSetting Caching (Phase 2.3)

**Files:**
- Modify: `app/Models/SecurityPolicy.php`
- Modify: `app/Models/SystemSetting.php`
- Modify: `app/Services/QRChallengeService.php` (swap to `getActive()`)
- Create: `tests/Unit/Services/CachingTest.php`

---

- [ ] **Step 3.1 — Write failing tests**

Run: `php artisan make:test --pest --unit CachingTest`
Then **replace** `tests/Unit/Services/CachingTest.php` with:

```php
<?php

use App\Models\SecurityPolicy;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('security_policy_active_is_cached_after_first_read', function () {
    SecurityPolicy::factory()->create(['is_active' => true]);

    SecurityPolicy::getActive(); // Populate cache

    DB::enableQueryLog();
    SecurityPolicy::getActive(); // Should use cache — no DB query
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

test('security_policy_cache_is_cleared_on_save', function () {
    Cache::spy();

    $policy = SecurityPolicy::factory()->create(['is_active' => true]);

    Cache::shouldHaveReceived('forget')->with('security_policy.active');
});

test('system_setting_get_is_cached_after_first_read', function () {
    SystemSetting::factory()->create(['key' => 'test_key', 'value' => 'hello']);

    SystemSetting::get('test_key'); // Populate cache

    DB::enableQueryLog();
    $result = SystemSetting::get('test_key'); // Should use cache
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
    expect($result)->toBe('hello');
});

test('system_setting_cache_is_cleared_on_set', function () {
    Cache::spy();

    SystemSetting::set('my_key', 'my_value');

    Cache::shouldHaveReceived('forget')->with('system_setting.my_key');
});
```

- [ ] **Step 3.2 — Run to confirm RED**

```bash
php artisan test --compact tests/Unit/Services/CachingTest.php
```

Expected: 4 failures — `SecurityPolicy::getActive()` not found; `Cache::forget` not called.

---

- [ ] **Step 3.3 — Add caching to `SecurityPolicy`**

Modify `app/Models/SecurityPolicy.php` — add the import and two methods (insert after the existing `scopeActive` method):

```php
use Illuminate\Support\Facades\Cache;
```

Add to the class body:

```php
public static function getActive(): ?static
{
    return Cache::remember('security_policy.active', 60, fn () => static::active()->first());
}

protected static function booted(): void
{
    static::saved(fn () => Cache::forget('security_policy.active'));
    static::deleted(fn () => Cache::forget('security_policy.active'));
}
```

- [ ] **Step 3.4 — Add caching to `SystemSetting`**

Modify `app/Models/SystemSetting.php` — add import and update both static methods:

```php
use Illuminate\Support\Facades\Cache;
```

Replace the existing `get()` and `set()` bodies:

```php
public static function get(string $key, mixed $default = null): mixed
{
    return Cache::remember("system_setting.{$key}", 60, fn () =>
        static::where('key', $key)->value('value') ?? $default
    );
}

public static function set(string $key, mixed $value): void
{
    static::updateOrCreate(['key' => $key], ['value' => $value]);
    Cache::forget("system_setting.{$key}");
}
```

- [ ] **Step 3.5 — Update `QRChallengeService` to use `getActive()`**

In `app/Services/QRChallengeService.php`, replace both occurrences of `SecurityPolicy::active()->first()` with `SecurityPolicy::getActive()`.

- [ ] **Step 3.6 — Run to confirm GREEN**

```bash
php artisan test --compact tests/Unit/Services/CachingTest.php
```

Expected: 4 passed.

- [ ] **Step 3.7 — Run full suite + pint + commit**

```bash
php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Models/SecurityPolicy.php app/Models/SystemSetting.php app/Services/QRChallengeService.php tests/Unit/Services/CachingTest.php
git commit -m "feat: add Cache::remember wrappers for SecurityPolicy and SystemSetting"
```

---

## Task 4 — AttendanceMarked Broadcast Event (Phase 2.4)

**Files:**
- Create: `app/Events/AttendanceMarked.php`
- Modify: `routes/channels.php`
- Create: `tests/Feature/Broadcasting/AttendanceMarkedTest.php`

> `BROADCAST_CONNECTION=null` in tests — use `Event::fake()` to assert broadcasting without a running Reverb server.

---

- [ ] **Step 4.1 — Write failing tests**

Create directory `tests/Feature/Broadcasting/` and run:
`php artisan make:test --pest BroadcastingAttendanceMarkedTest`
Then move and **replace** as `tests/Feature/Broadcasting/AttendanceMarkedTest.php`:

```php
<?php

use App\Enums\AttendanceStatus;
use App\Events\AttendanceMarked;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('event_broadcasts_on_correct_private_channel', function () {
    $session = AttendanceSession::factory()->create();

    $event = new AttendanceMarked(
        session: $session,
        studentName: 'Alice',
        status: AttendanceStatus::Present->value,
        riskScore: 0,
        markedAt: now()->toIso8601String(),
        sessionStats: ['total_present' => 1, 'total_enrolled' => 15],
    );

    $channels = $event->broadcastOn();
    $channel = is_array($channels) ? $channels[0] : $channels;

    expect($channel)->toBeInstanceOf(PrivateChannel::class);
    expect($channel->name)->toBe("private-session.{$session->uuid}");
});

test('event_payload_contains_required_fields', function () {
    $session = AttendanceSession::factory()->create();

    $event = new AttendanceMarked(
        session: $session,
        studentName: 'Bob',
        status: AttendanceStatus::Late->value,
        riskScore: 15,
        markedAt: now()->toIso8601String(),
        sessionStats: ['total_present' => 5, 'total_enrolled' => 20],
    );

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys(['student_name', 'status', 'risk_score', 'marked_at', 'session_stats']);
    expect($payload['student_name'])->toBe('Bob');
    expect($payload['risk_score'])->toBe(15);
    expect($payload['session_stats']['total_enrolled'])->toBe(20);
});

test('faculty_can_listen_to_own_session_channel', function () {
    $facultyUser = User::factory()->faculty()->create();
    $faculty = Faculty::factory()->create(['user_id' => $facultyUser->id]);
    $session = AttendanceSession::factory()->create(['faculty_id' => $faculty->id]);

    $response = $this->actingAs($facultyUser)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-session.{$session->uuid}",
            'socket_id' => '1234.5678',
        ]);

    $response->assertSuccessful();
});

test('faculty_cannot_listen_to_another_facultys_session_channel', function () {
    $facultyUser = User::factory()->faculty()->create();
    $otherFaculty = Faculty::factory()->create();
    $session = AttendanceSession::factory()->create(['faculty_id' => $otherFaculty->id]);

    $response = $this->actingAs($facultyUser)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-session.{$session->uuid}",
            'socket_id' => '1234.5678',
        ]);

    $response->assertForbidden();
});
```

- [ ] **Step 4.2 — Run to confirm RED**

```bash
php artisan test --compact tests/Feature/Broadcasting/AttendanceMarkedTest.php
```

Expected: failures — `AttendanceMarked` class not found; channel not authorised.

---

- [ ] **Step 4.3 — Create `AttendanceMarked` event**

Create `app/Events/AttendanceMarked.php`:

```php
<?php

namespace App\Events;

use App\Models\AttendanceSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceMarked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AttendanceSession $session,
        public readonly string $studentName,
        public readonly string $status,
        public readonly int $riskScore,
        public readonly string $markedAt,
        public readonly array $sessionStats,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("session.{$this->session->uuid}");
    }

    public function broadcastAs(): string
    {
        return 'AttendanceMarked';
    }

    public function broadcastWith(): array
    {
        return [
            'student_name' => $this->studentName,
            'status' => $this->status,
            'risk_score' => $this->riskScore,
            'marked_at' => $this->markedAt,
            'session_stats' => $this->sessionStats,
        ];
    }
}
```

- [ ] **Step 4.4 — Register session channel in `routes/channels.php`**

Replace `routes/channels.php` with:

```php
<?php

use App\Enums\UserRole;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('session.{sessionUuid}', function ($user, string $sessionUuid) {
    $session = AttendanceSession::where('uuid', $sessionUuid)->first();

    if (! $session) {
        return false;
    }

    if ($user->role === UserRole::SuperAdmin) {
        return true;
    }

    if ($user->role === UserRole::Faculty) {
        $faculty = Faculty::where('user_id', $user->id)->first();

        return $faculty && $session->faculty_id === $faculty->id;
    }

    if ($user->role === UserRole::Admin) {
        return $user->activeAdminAssignment?->department_id === $session->course->department_id;
    }

    return false;
});
```

- [ ] **Step 4.5 — Run to confirm GREEN**

```bash
php artisan test --compact tests/Feature/Broadcasting/AttendanceMarkedTest.php
```

Expected: 4 passed.

- [ ] **Step 4.6 — Run full suite + pint + commit**

```bash
php artisan test --compact
/Users/thomas/.config/herd-lite/bin/php vendor/bin/pint --dirty --format agent
git add app/Events/AttendanceMarked.php routes/channels.php tests/Feature/Broadcasting/AttendanceMarkedTest.php
git commit -m "feat: add AttendanceMarked broadcast event with private channel authorisation"
```

---

## Post-Phase

- [ ] **Update `docs/project-phases.md`** — mark phases 2.1–2.4 ✅

```bash
git add docs/project-phases.md
git commit -m "docs: mark Phase 2 complete"
```

---

## Self-Review Checklist

- **Spec 2.1:** `generateForSession` → payload, HMAC, Redis TTL, base64 PNG ✅ | `validateScan` → HMAC verify, expiry, session match ✅ | `SecurityPolicy::active()` used ✅
- **Spec 2.2:** `LogsToAudit` trait ✅ | wraps `AuditLog::record()` ✅ | 5 feature tests ✅ (plus 1 trait-specific test)
- **Spec 2.3:** `SecurityPolicy::getActive()` cached with key `security_policy.active`, TTL 60 ✅ | `saved` observer busts cache ✅ | `SystemSetting::get()` cached ✅ | `SystemSetting::set()` busts cache ✅ | `QRChallengeService` updated to `getActive()` ✅
- **Spec 2.4:** `ShouldBroadcast` ✅ | `PrivateChannel("session.{uuid}")` ✅ | `broadcastAs() = 'AttendanceMarked'` ✅ | payload fields ✅ | channel auth: faculty owner + super admin + dept admin ✅
- **No placeholders** ✅
- **Type consistency:** `makeQrPayload()` helper in test file matches service's HMAC logic ✅ | `SecurityPolicy::getActive()` referenced in Task 1.4 used via update in Task 3.5 ✅
