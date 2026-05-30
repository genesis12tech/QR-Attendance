<?php

use App\Http\Controllers\Api\V1\Student\AttendanceController;
use App\Http\Controllers\Api\V1\Student\AuthController;
use App\Http\Controllers\Api\V1\Student\DeviceController;
use App\Http\Controllers\Api\V1\Student\ScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/student')->group(function () {
    Route::prefix('auth')->middleware('throttle:60,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
        Route::get('devices', [DeviceController::class, 'index']);
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices/{device}', [DeviceController::class, 'destroy']);
        Route::get('attendance', [AttendanceController::class, 'index']);
        Route::get('attendance/summary', [AttendanceController::class, 'summary']);
    });

    Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('attendance/scan', [ScanController::class, 'store']);
    });
});
