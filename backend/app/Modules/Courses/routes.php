<?php

use App\Modules\Courses\Http\Controllers\Admin\BatchController;
use App\Modules\Courses\Http\Controllers\Admin\ContentController;
use App\Modules\Courses\Http\Controllers\Admin\CourseController;
use App\Modules\Courses\Http\Controllers\Admin\FolderController;
use App\Modules\Courses\Http\Controllers\App\LearningController;
use App\Modules\Courses\Http\Controllers\App\StoreController;
use Illuminate\Support\Facades\Route;

// ---------- store (guest or logged in) ----------
Route::prefix('public')->group(function () {
    Route::get('courses', [StoreController::class, 'index']);
    Route::get('courses/{slug}', [StoreController::class, 'show']);
});

// ---------- student / teacher app ----------
Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    Route::get('my-courses', [LearningController::class, 'myCourses']);
    Route::get('courses/{course}/browse', [LearningController::class, 'browse']);
    Route::post('courses/{course}/class-alerts', [LearningController::class, 'classAlerts']);
    Route::get('contents/{content}', [LearningController::class, 'open']);
    Route::post('contents/{content}/progress', [LearningController::class, 'progress']);
});

// ---------- admin panel ----------
Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('courses', [CourseController::class, 'index'])->middleware('permission:courses.view');
    Route::get('courses/options', [CourseController::class, 'options']);
    Route::post('courses', [CourseController::class, 'store'])->middleware('permission:courses.create');
    Route::get('courses/{course}', [CourseController::class, 'show']);
    Route::post('courses/{course}', [CourseController::class, 'update']);          // POST for multipart thumbnail
    Route::patch('courses/{course}', [CourseController::class, 'update']);
    Route::post('courses/{course}/publish', [CourseController::class, 'publish']);
    Route::post('courses/{course}/unpublish', [CourseController::class, 'unpublish']);
    Route::post('courses/{course}/archive', [CourseController::class, 'archive']);
    Route::put('courses/{course}/staff', [CourseController::class, 'staff']);
    Route::get('courses/{course}/share', [CourseController::class, 'share']);
    Route::delete('courses/{course}', [CourseController::class, 'destroy']);

    Route::get('courses/{course}/batches', [BatchController::class, 'index']);
    Route::post('courses/{course}/batches', [BatchController::class, 'store']);
    Route::patch('batches/{batch}', [BatchController::class, 'update']);
    Route::post('batches/{batch}/clone', [BatchController::class, 'clone']);
    Route::post('batches/{batch}/enrollment', [BatchController::class, 'toggleEnrollment']);
    Route::delete('batches/{batch}', [BatchController::class, 'destroy'])->middleware('permission:batches.delete');

    Route::get('courses/{course}/folders', [FolderController::class, 'index']);
    Route::post('courses/{course}/folders', [FolderController::class, 'store']);
    Route::post('courses/{course}/folders/reorder', [FolderController::class, 'reorder']);
    Route::patch('folders/{folder}', [FolderController::class, 'update']);
    Route::delete('folders/{folder}', [FolderController::class, 'destroy']);

    Route::get('courses/{course}/contents', [ContentController::class, 'index']);
    Route::post('courses/{course}/contents', [ContentController::class, 'store']);
    Route::post('courses/{course}/contents/reorder', [ContentController::class, 'reorder']);
    Route::post('contents/{content}', [ContentController::class, 'update']);         // multipart (replace PDF)
    Route::patch('contents/{content}', [ContentController::class, 'update']);
    Route::delete('contents/{content}', [ContentController::class, 'destroy']);
});
