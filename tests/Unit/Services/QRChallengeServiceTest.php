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
        ->withArgs(fn ($key, $value, $ttl) => str_starts_with($key, "qr:{$session->uuid}:") && $ttl === 45
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

    // Payload signed for $otherSession but validated against $session
    $payload = makeQrPayload($otherSession->uuid, now()->timestamp);

    expect(app(QRChallengeService::class)->validateScan($payload, $session))->toBeFalse();
});

test('validate_scan_allows_clock_skew_within_policy_tolerance', function () {
    $session = AttendanceSession::factory()->create();
    // Total window = 30 + 10 = 40 seconds
    SecurityPolicy::factory()->create(['qr_expiry_seconds' => 30, 'clock_skew_seconds' => 10, 'is_active' => true]);

    // 35s ago — outside qr_expiry (30) but inside total window (40)
    $payload = makeQrPayload($session->uuid, now()->subSeconds(35)->timestamp);

    expect(app(QRChallengeService::class)->validateScan($payload, $session))->toBeTrue();
});

// ── Helper ────────────────────────────────────────────────────────────────────

function makeQrPayload(string $sessionUuid, int $issuedAt): string
{
    $nonce = 'test-nonce-'.str_replace(['.', ' '], '-', microtime());
    $inner = ['session_uuid' => $sessionUuid, 'nonce' => $nonce, 'issued_at' => $issuedAt];
    ksort($inner);
    $hmac = hash_hmac('sha256', json_encode($inner), config('services.qr_secret'));
    $payload = base64_encode(json_encode(array_merge($inner, ['hmac' => $hmac])));

    Cache::put("qr:{$sessionUuid}:{$nonce}", $payload, 60);

    return $payload;
}
