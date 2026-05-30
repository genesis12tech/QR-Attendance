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

        $student = $request->user()->student;
        abort_unless($student !== null, 403, 'No student profile associated with this account.');

        $result = $this->scanService->scan(
            student: $student,
            qrPayload: $data['qr_payload'],
            deviceFingerprint: $data['device_fingerprint'],
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
        );

        return response()->json($result);
    }
}
