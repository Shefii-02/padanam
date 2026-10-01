<?php

use App\Modules\Daily\Http\Controllers\DailyController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    Route::get('daily-quiz', [DailyController::class, 'today']);
    Route::get('study-plan', [DailyController::class, 'myPlan']);
    Route::post('study-plan/tasks', [DailyController::class, 'addTask']);
    Route::post('study-plan/tasks/{task}/toggle', [DailyController::class, 'toggleTask']);
    Route::delete('study-plan/tasks/{task}', [DailyController::class, 'deleteTask']);
});

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::middleware('permission:daily_quiz.manage')->group(function () {
        Route::get('daily-quiz', [DailyController::class, 'calendar']);
        Route::post('daily-quiz', [DailyController::class, 'plan']);
        Route::post('daily-quiz/range', [DailyController::class, 'planRange']);
        Route::post('daily-quiz/{dailyQuiz}/build', [DailyController::class, 'build']);
        Route::delete('daily-quiz/{dailyQuiz}', [DailyController::class, 'destroy']);
    });
    Route::middleware('permission:study_plans.manage')->group(function () {
        Route::get('study-plans', [DailyController::class, 'templates']);
        Route::post('study-plans', [DailyController::class, 'saveTemplate']);
        Route::put('study-plans/{plan}', [DailyController::class, 'saveTemplate']);
    });
});
