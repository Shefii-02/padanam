<?php

use App\Modules\QuestionBank\Http\Controllers\FolderLabelController;
use App\Modules\QuestionBank\Http\Controllers\ImportController;
use App\Modules\QuestionBank\Http\Controllers\QuestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/question-bank')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::middleware('permission:question_bank.view')->group(function () {
        Route::get('questions', [QuestionController::class, 'index']);
        Route::get('questions/facets', [QuestionController::class, 'facets']);
        Route::get('questions/{question}', [QuestionController::class, 'show']);
        Route::get('folders', [FolderLabelController::class, 'folders']);
        Route::get('labels', [FolderLabelController::class, 'labels']);
    });
    Route::post('questions', [QuestionController::class, 'store'])->middleware('permission:question_bank.create');
    Route::patch('questions/{question}', [QuestionController::class, 'update'])->middleware('permission:question_bank.edit');
    Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->middleware('permission:question_bank.delete');
    Route::post('questions/bulk', [QuestionController::class, 'bulk'])->middleware('permission:question_bank.edit');

    Route::middleware('permission:question_bank.edit')->group(function () {
        Route::post('folders', [FolderLabelController::class, 'storeFolder']);
        Route::patch('folders/{folder}', [FolderLabelController::class, 'updateFolder']);
        Route::delete('folders/{folder}', [FolderLabelController::class, 'destroyFolder']);
        Route::post('labels', [FolderLabelController::class, 'storeLabel']);
        Route::patch('labels/{label}', [FolderLabelController::class, 'updateLabel']);
        Route::delete('labels/{label}', [FolderLabelController::class, 'destroyLabel']);
        Route::post('labels/{label}/merge', [FolderLabelController::class, 'mergeLabel']);
    });

    Route::middleware('permission:question_bank.import')->group(function () {
        Route::get('imports', [ImportController::class, 'index']);
        Route::get('imports/template', [ImportController::class, 'template']);
        Route::post('imports', [ImportController::class, 'upload']);
        Route::get('imports/{import}', [ImportController::class, 'show']);
        Route::post('imports/{import}/confirm', [ImportController::class, 'confirm']);
        Route::get('imports/{import}/errors', [ImportController::class, 'errors']);
    });
});
