<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\AttendanceScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function __construct(
        private AttendanceScanService $scanService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_payload' => ['required', 'string'],
            'device_fingerprint' => ['required', 'string'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $result = $this->scanService->scan(
            student: $request->user()->student,
            qrPayload: $data['qr_payload'],
            deviceFingerprint: $data['device_fingerprint'],
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
        );

        return response()->json($result);
    }
}
