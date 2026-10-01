<?php

use App\Modules\AppFeed\Http\Controllers\AppFeedController;
use Illuminate\Support\Facades\Route;

Route::get('public/app/config', [AppFeedController::class, 'config']);
Route::get('public/setup/options', [AppFeedController::class, 'setupOptions']);

Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    Route::get('home', [AppFeedController::class, 'home']);
    Route::get('profile-page', [AppFeedController::class, 'profile']);
    Route::get('exams', [AppFeedController::class, 'exams']);
    Route::get('exams/{slug}', [AppFeedController::class, 'examHub']);
});
