<?php

use App\Modules\Leads\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

Route::post('app/events', [LeadController::class, 'track'])->middleware(['auth:api', 'active', 'throttle:120,1']);

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('leads', [LeadController::class, 'index'])->middleware('permission:leads.view');
    Route::get('leads/export', [LeadController::class, 'export'])->middleware('permission:leads.export');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->middleware('permission:leads.view');
    Route::middleware('permission:leads.manage')->group(function () {
        Route::post('leads', [LeadController::class, 'store']);
        Route::post('leads/bulk', [LeadController::class, 'bulk']);
        Route::patch('leads/{lead}', [LeadController::class, 'update']);
        Route::post('leads/{lead}/activity', [LeadController::class, 'activity']);
    });
});
