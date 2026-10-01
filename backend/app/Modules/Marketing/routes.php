<?php

use App\Modules\Marketing\Http\Controllers\MarketingController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/marketing')->middleware(['auth:api', 'active', 'staff', 'permission:marketing.export'])->group(function () {
    Route::get('types', [MarketingController::class, 'types']);
    Route::get('count', [MarketingController::class, 'count']);
    Route::get('export', [MarketingController::class, 'export'])->middleware('throttle:20,1');
    Route::get('exports', [MarketingController::class, 'history']);
});
