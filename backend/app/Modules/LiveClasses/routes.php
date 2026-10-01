<?php

use App\Modules\LiveClasses\Http\Controllers\LiveClassController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    Route::get('live-classes', [LiveClassController::class, 'upcoming']);
    Route::get('live-classes/{liveClass}/join', [LiveClassController::class, 'join']);
    // teachers can go live from the app too (permission checked inside)
    Route::post('live-classes/{liveClass}/go-live', [LiveClassController::class, 'goLive']);
    Route::post('live-classes/{liveClass}/end', [LiveClassController::class, 'end']);
});

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('live-classes', [LiveClassController::class, 'index'])->middleware('permission:live_classes.view');
    Route::post('live-classes', [LiveClassController::class, 'store'])->middleware('permission:live_classes.schedule');
    Route::patch('live-classes/{liveClass}', [LiveClassController::class, 'update']);
    Route::post('live-classes/{liveClass}/go-live', [LiveClassController::class, 'goLive']);
    Route::post('live-classes/{liveClass}/end', [LiveClassController::class, 'end']);
    Route::post('live-classes/{liveClass}/cancel', [LiveClassController::class, 'cancel']);
    Route::get('live-classes/{liveClass}/attendance', [LiveClassController::class, 'attendance']);
});
