<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::get('otp/channel', [AuthController::class, 'otpChannel']);
    Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:10,1');
    Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');
    Route::post('login', [AuthController::class, 'passwordLogin'])->middleware('throttle:10,1');   // admin panel

    Route::middleware(['auth:api', 'active'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('device', [AuthController::class, 'device']);
    });
});
