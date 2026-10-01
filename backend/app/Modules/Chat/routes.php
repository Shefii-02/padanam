<?php

use App\Modules\Chat\Http\Controllers\ChatController;
use App\Modules\Chat\Http\Controllers\InternalController;
use App\Modules\Chat\Http\Middleware\InternalKey;
use Illuminate\Support\Facades\Route;

Route::get('public/invites/{code}', [ChatController::class, 'invitePreview']);

Route::prefix('app/chat')->middleware(['auth:api', 'active', 'permission:chat.use'])->group(function () {
    Route::get('rooms', [ChatController::class, 'rooms']);
    Route::get('contacts', [ChatController::class, 'contacts']);
    Route::post('direct/{user}', [ChatController::class, 'direct']);
    Route::post('join/{code}', [ChatController::class, 'join'])->middleware('throttle:20,1');
    Route::post('groups', [ChatController::class, 'createGroup']);
    Route::get('rooms/{room}', [ChatController::class, 'show']);
    Route::get('rooms/{room}/messages', [ChatController::class, 'messages']);
    Route::get('rooms/{room}/members', [ChatController::class, 'members']);
    Route::post('rooms/{room}/upload', [ChatController::class, 'upload'])->middleware('throttle:30,1');
    Route::post('rooms/{room}/leave', [ChatController::class, 'leave']);
    Route::post('rooms/{room}/prefs', [ChatController::class, 'myPrefs']);
    Route::patch('rooms/{room}', [ChatController::class, 'updateGroup']);
    Route::post('rooms/{room}/invite/reset', [ChatController::class, 'resetInvite']);
    Route::post('rooms/{room}/members', [ChatController::class, 'addMembers']);
    Route::delete('rooms/{room}/members/{user}', [ChatController::class, 'removeMember']);
    Route::post('rooms/{room}/members/{user}/role', [ChatController::class, 'setRole']);
    Route::post('rooms/{room}/members/{user}/mute', [ChatController::class, 'mute']);
    Route::get('rooms/{room}/requests', [ChatController::class, 'requests']);
    Route::post('requests/{joinRequest}', [ChatController::class, 'handleRequest']);
    Route::delete('messages/{message}', [ChatController::class, 'deleteMessage']);
    Route::post('messages/{message}/report', [ChatController::class, 'report']);
});

Route::prefix('admin/chat')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::get('rooms', [ChatController::class, 'adminRooms'])->middleware('permission:chat.manage_groups');
    Route::delete('rooms/{room}', [ChatController::class, 'destroy'])->middleware('permission:chat.manage_groups');
    Route::get('reports', [ChatController::class, 'reports'])->middleware('permission:chat.moderate');
    Route::post('reports/{report}', [ChatController::class, 'handleReport'])->middleware('permission:chat.moderate');
    Route::get('policies', [ChatController::class, 'policies'])->middleware('permission:chat.manage_permissions');
    Route::put('policies', [ChatController::class, 'savePolicies'])->middleware('permission:chat.manage_permissions');
});

Route::prefix('internal')->middleware(InternalKey::class)->group(function () {
    Route::post('chat/notify', [InternalController::class, 'notifyMessage']);
});
