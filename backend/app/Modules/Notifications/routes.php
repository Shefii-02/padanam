<?php

use App\Modules\Notifications\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;

Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    Route::get('notifications', [InboxController::class, 'index']);
    Route::post('notifications/read', [InboxController::class, 'read']);
    Route::post('notifications/{notification}/opened', [InboxController::class, 'opened']);
    Route::get('notification-preferences', [InboxController::class, 'preferences']);
    Route::post('notification-preferences', [InboxController::class, 'updatePreference']);
});

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff', 'permission:notifications.send'])->group(function () {
    Route::get('campaigns', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'index']);
    Route::get('notification-channels', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'channels']);
    Route::post('campaigns/preview', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'preview']);
    Route::post('campaigns', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'store']);
    Route::patch('campaigns/{campaign}', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'update']);
    Route::post('campaigns/{campaign}/send', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'send']);
    Route::post('campaigns/{campaign}/cancel', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'cancel']);
    Route::get('notification-templates', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'templates'])->middleware('permission:notifications.manage_templates');
    Route::patch('notification-templates/{template}', [\App\Modules\Notifications\Http\Controllers\CampaignController::class, 'updateTemplate'])->middleware('permission:notifications.manage_templates');
});
