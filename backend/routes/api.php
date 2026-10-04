<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BloodRequestController;
use App\Http\Controllers\Api\V1\DonorProfileController;
use App\Http\Controllers\Api\V1\OperationsController;
use App\Http\Controllers\Api\V1\ReferenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/reference-data', [ReferenceController::class, 'index']);
    Route::get('/facilities', [ReferenceController::class, 'facilities']);

    Route::prefix('auth')->middleware('throttle:10,1')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::patch('/donor/profile', [DonorProfileController::class, 'update']);
        Route::get('/notifications', [OperationsController::class, 'notifications']);
        Route::patch('/notifications/{notification}/read', [OperationsController::class, 'markNotificationRead']);
        Route::get('/blood-requests', [BloodRequestController::class, 'index']);
        Route::post('/blood-requests', [BloodRequestController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('/blood-requests/{bloodRequest}/cancel', [BloodRequestController::class, 'cancel']);

        Route::prefix('operations')->middleware('role:staff,admin')->group(function (): void {
            Route::get('/dashboard', [OperationsController::class, 'dashboard']);
            Route::get('/inventory', [OperationsController::class, 'inventory']);
            Route::post('/donations', [OperationsController::class, 'storeDonation']);
            Route::patch('/donations/{donation}/status', [OperationsController::class, 'updateDonationStatus']);
            Route::get('/donors', [OperationsController::class, 'donors']);
            Route::patch('/donors/{donor}/verification', [OperationsController::class, 'updateDonorVerification']);
            Route::post('/blood-requests/{bloodRequest}/review', [BloodRequestController::class, 'review']);
            Route::get('/blood-requests/{bloodRequest}/matches', [BloodRequestController::class, 'matches']);
            Route::post('/blood-requests/{bloodRequest}/fulfill', [BloodRequestController::class, 'fulfill']);
        });

        Route::prefix('admin')->middleware('role:admin')->group(function (): void {
            Route::get('/dashboard', [OperationsController::class, 'dashboard']);
            Route::get('/inventory', [OperationsController::class, 'inventory']);
            Route::get('/donors', [OperationsController::class, 'donors']);
            Route::get('/audit-logs', [OperationsController::class, 'auditLogs']);
            Route::post('/facilities', [OperationsController::class, 'storeFacility']);
            Route::patch('/facilities/{facility}/verification', [OperationsController::class, 'verifyFacility']);
            Route::put('/facilities/{facility}/staff/{user}', [OperationsController::class, 'assignStaff']);
            Route::delete('/facilities/{facility}/staff/{user}', [OperationsController::class, 'unassignStaff']);
        });
    });
});
