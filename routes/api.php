<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BandController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:8,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/availability', [AvailabilityController::class, 'index']);
        Route::put('/availability', [AvailabilityController::class, 'update']);
        Route::get('/band/members', [BandController::class, 'members']);
        Route::post('/band/invite-code/regenerate', [BandController::class, 'regenerateInviteCode']);
    });
});
