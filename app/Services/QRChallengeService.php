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

        if (! is_array($data)
            || ! array_key_exists('session_uuid', $data)
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
