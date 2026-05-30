<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegistration;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = DeviceRegistration::where('user_id', $request->user()->id)->get();

        return response()->json($devices);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_fingerprint' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $maxDevices = (int) SystemSetting::get('max_devices_per_student', '1');
        $currentCount = DeviceRegistration::where('user_id', $request->user()->id)->count();

        if ($currentCount >= $maxDevices) {
            throw ValidationException::withMessages([
                'device_fingerprint' => ["You have reached the maximum of {$maxDevices} registered device(s)."],
            ]);
        }

        $device = DeviceRegistration::create([
            'user_id' => $request->user()->id,
            'device_fingerprint' => $data['device_fingerprint'],
            'device_name' => $data['device_name'] ?? null,
            'platform' => $data['platform'] ?? null,
            'app_version' => $data['app_version'] ?? null,
            'last_seen_at' => now(),
        ]);

        return response()->json($device, 201);
    }

    public function destroy(Request $request, DeviceRegistration $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 403);

        $device->delete();

        return response()->json(['message' => 'Device removed.']);
    }
}
