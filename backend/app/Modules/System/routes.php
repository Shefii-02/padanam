<?php

use App\Modules\System\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

Route::get('public/app-version', [SystemController::class, 'appVersion']);

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('app-versions', [SystemController::class, 'versions'])->middleware('permission:app_versions.manage');
    Route::put('app-versions/{platform}', [SystemController::class, 'saveVersion'])->middleware('permission:app_versions.manage');
    Route::get('settings', [SystemController::class, 'settings'])->middleware('permission:settings.manage');
    Route::put('settings', [SystemController::class, 'saveSettings'])->middleware('permission:settings.manage');
});
