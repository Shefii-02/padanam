<?php

use App\Modules\Users\Http\Controllers\ProfileController;
use App\Modules\Users\Http\Controllers\StaffAdminController;
use App\Modules\Users\Http\Controllers\StudentAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile/setup', [ProfileController::class, 'setup']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/photo', [ProfileController::class, 'photo']);
});

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('students', [StudentAdminController::class, 'index'])->middleware('permission:users.view');
    Route::get('students/summary', [StudentAdminController::class, 'summary'])->middleware('permission:users.view');
    Route::get('students/export', [StudentAdminController::class, 'export'])->middleware('permission:users.export');
    Route::get('students/{user}', [StudentAdminController::class, 'show'])->middleware('permission:users.view');
    Route::post('students/{user}/block', [StudentAdminController::class, 'block'])->middleware('permission:users.block');
    Route::post('students/{user}/unblock', [StudentAdminController::class, 'unblock'])->middleware('permission:users.block');

    Route::get('staff', [StaffAdminController::class, 'index'])->middleware('permission:roles.view');
    Route::get('staff/options', [StaffAdminController::class, 'options']);
    Route::post('staff', [StaffAdminController::class, 'store'])->middleware('permission:users.create');
    Route::patch('staff/{user}', [StaffAdminController::class, 'update'])->middleware('permission:users.edit');
    Route::post('staff/{user}/block', [StaffAdminController::class, 'block'])->middleware('permission:users.block');
    Route::post('staff/{user}/unblock', [StaffAdminController::class, 'unblock'])->middleware('permission:users.block');
});
