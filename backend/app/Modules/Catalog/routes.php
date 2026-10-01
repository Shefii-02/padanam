<?php

use App\Modules\Catalog\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('categories', [CatalogController::class, 'publicTree']);
    Route::get('exams/{exam:slug}', [CatalogController::class, 'exam']);
});

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('categories', [CatalogController::class, 'tree'])->middleware('permission:categories.view');
    Route::middleware('permission:categories.manage')->group(function () {
        Route::post('categories', [CatalogController::class, 'store']);
        Route::post('categories/reorder', [CatalogController::class, 'reorder']);
        Route::patch('categories/{category}', [CatalogController::class, 'update']);
        Route::delete('categories/{category}', [CatalogController::class, 'destroy']);
        Route::post('exams', [CatalogController::class, 'storeExam']);
        Route::patch('exams/{exam}', [CatalogController::class, 'updateExam']);
        Route::delete('exams/{exam}', [CatalogController::class, 'destroyExam']);
    });
});
