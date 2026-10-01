<?php

use App\Modules\Roles\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
    Route::get('permissions', [RoleController::class, 'catalog'])->middleware('permission:roles.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.manage');
    Route::patch('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.manage');
    Route::post('roles/{role}/toggle', [RoleController::class, 'toggle'])->middleware('permission:roles.manage');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.manage');
});
