<?php

use App\Modules\Tests\Http\Controllers\Admin\TestController;
use App\Modules\Tests\Http\Controllers\App\TestPlayController;
use App\Modules\Tests\Http\Controllers\App\TestViewController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    // screen-shaped (Flutter test engine)
    Route::get('test-series', [TestViewController::class, 'series']);
    Route::get('tests/{test}/instructions', [TestViewController::class, 'instructions']);
    Route::get('attempts/{attempt}/summary', [TestViewController::class, 'summary']);
    Route::get('attempts/{attempt}/analysis', [TestViewController::class, 'analysis']);
    Route::get('attempts/{attempt}/review', [TestViewController::class, 'review']);
    Route::get('performance', [TestViewController::class, 'performance']);

    Route::get('tests', [TestPlayController::class, 'index']);
    Route::get('tests/history', [TestPlayController::class, 'history']);
    Route::get('tests/{test}', [TestPlayController::class, 'show']);
    Route::post('tests/{test}/start', [TestPlayController::class, 'start'])->middleware('throttle:20,1');
    Route::get('tests/{test}/leaderboard', [TestPlayController::class, 'leaderboard']);
    Route::post('tests/{test}/omr', [TestPlayController::class, 'uploadOmr']);
    Route::get('attempts/{attempt}', [TestPlayController::class, 'paper']);
    Route::post('attempts/{attempt}/sync', [TestPlayController::class, 'sync'])->middleware('throttle:120,1');
    Route::post('attempts/{attempt}/next-section', [TestPlayController::class, 'nextSection']);
    Route::post('attempts/{attempt}/submit', [TestPlayController::class, 'submit']);
    Route::get('attempts/{attempt}/result', [TestPlayController::class, 'result']);
    Route::get('attempts/{attempt}/solutions', [TestPlayController::class, 'solutions']);
    Route::post('question-reports', [TestPlayController::class, 'reportQuestion'])->middleware('throttle:20,1');
});

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('tests', [TestController::class, 'index'])->middleware('permission:tests.view');
    Route::post('tests', [TestController::class, 'store'])->middleware('permission:tests.create');
    Route::get('tests/{test}', [TestController::class, 'show'])->middleware('permission:tests.view');
    Route::delete('tests/{test}', [TestController::class, 'destroy']);
    Route::post('tests/{test}/publish', [TestController::class, 'publish']);
    Route::post('tests/{test}/unpublish', [TestController::class, 'unpublish']);

    Route::middleware('permission:tests.edit')->group(function () {
        Route::patch('tests/{test}', [TestController::class, 'update']);
        Route::post('tests/{test}/duplicate', [TestController::class, 'duplicate']);
        Route::post('tests/{test}/sections', [TestController::class, 'storeSection']);
        Route::patch('test-sections/{section}', [TestController::class, 'updateSection']);
        Route::delete('test-sections/{section}', [TestController::class, 'destroySection']);
        Route::get('tests/{test}/questions', [TestController::class, 'questions']);
        Route::post('tests/{test}/questions', [TestController::class, 'addQuestions']);
        Route::post('tests/{test}/questions/auto-pick', [TestController::class, 'autoPick']);
        Route::post('tests/{test}/questions/remove', [TestController::class, 'removeQuestions']);
        Route::post('tests/{test}/questions/arrange', [TestController::class, 'arrange']);
    });

    Route::middleware('permission:tests.view')->group(function () {
        Route::get('tests/{test}/results', [TestController::class, 'results']);
        Route::get('tests/{test}/results/export', [TestController::class, 'exportResults']);
        Route::get('tests/{test}/answer-key', [TestController::class, 'answerKey']);
        Route::get('tests/{test}/omr-sheet', [TestController::class, 'omrSheet']);
        Route::get('tests/{test}/omr/pending', [TestController::class, 'omrPending']);
    });
    Route::post('tests/{test}/publish-result', [TestController::class, 'publishResult']);
    Route::post('tests/{test}/omr/import', [TestController::class, 'omrImport']);
    Route::post('tests/{test}/omr/enter', [TestController::class, 'omrEnter']);
});
