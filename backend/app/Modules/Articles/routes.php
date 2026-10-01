<?php

use App\Modules\Articles\Http\Controllers\ArticleController;
use Illuminate\Support\Facades\Route;

Route::get('public/articles', [ArticleController::class, 'index']);
Route::get('public/articles/{slug}', [ArticleController::class, 'show']);

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('articles', [ArticleController::class, 'adminIndex'])->middleware('permission:articles.view');
    Route::get('articles/{article}', [ArticleController::class, 'adminShow'])->middleware('permission:articles.view');
    Route::middleware('permission:articles.manage')->group(function () {
        Route::post('articles', [ArticleController::class, 'store']);
        Route::post('articles/{article}', [ArticleController::class, 'update']);    // multipart cover
        Route::patch('articles/{article}', [ArticleController::class, 'update']);
        Route::delete('articles/{article}', [ArticleController::class, 'destroy']);
    });
});
