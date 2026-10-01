<?php

use App\Modules\Security\Http\Controllers\SecurityController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/security')->middleware(['auth:api', 'active', 'staff', 'permission:security.view'])->group(function () {
    Route::get('otp-logs', [SecurityController::class, 'otpLogs']);
    Route::get('otp-logs/export', [SecurityController::class, 'exportOtpLogs']);
    Route::get('logins', [SecurityController::class, 'logins']);
    Route::get('audit', [SecurityController::class, 'audit']);
});
