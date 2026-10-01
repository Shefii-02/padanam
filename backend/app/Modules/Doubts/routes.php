<?php

use App\Modules\Doubts\Http\Controllers\DoubtController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    Route::get('doubts', [DoubtController::class, 'mine']);
    Route::post('doubts', [DoubtController::class, 'ask'])->middleware('throttle:15,1');
    Route::get('courses/{course}/doubts', [DoubtController::class, 'course']);
});

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('doubts', [DoubtController::class, 'inbox'])->middleware('permission:doubts.view');
    Route::post('doubts/{doubt}/answer', [DoubtController::class, 'answer'])->middleware('permission:doubts.answer');
});
