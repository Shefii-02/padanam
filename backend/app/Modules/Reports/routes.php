<?php

use App\Modules\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('dashboard', [ReportController::class, 'dashboard'])->middleware('permission:dashboard.view');
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/activity', [ReportController::class, 'activity']);
        Route::get('reports/performance', [ReportController::class, 'performance']);
        Route::get('reports/{type}/export', [ReportController::class, 'export']);
    });
});
