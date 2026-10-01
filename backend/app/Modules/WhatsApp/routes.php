<?php

use App\Modules\WhatsApp\Http\Controllers\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/whatsapp')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('status', [WhatsAppController::class, 'status']);   // used by admission / payment screens
    Route::middleware('permission:whatsapp.manage')->group(function () {
        Route::get('settings', [WhatsAppController::class, 'settings']);
        Route::put('accounts/{purpose}', [WhatsAppController::class, 'saveAccount']);
        Route::put('templates', [WhatsAppController::class, 'saveTemplates']);
        Route::post('accounts/{purpose}/test', [WhatsAppController::class, 'test'])->middleware('throttle:10,1');
    });
    Route::get('messages', [WhatsAppController::class, 'messages'])->middleware('permission:whatsapp.view');
    Route::post('messages/{message}/retry', [WhatsAppController::class, 'retry'])->middleware('permission:whatsapp.send');
    Route::post('send', [WhatsAppController::class, 'send'])->middleware(['permission:whatsapp.send', 'throttle:30,1']);
});
