<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Written mostly by the Node.js realtime service; Laravel reads them and manages groups from the admin panel.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['direct', 'group', 'batch_group', 'broadcast', 'staff']);
            $t->string('name')->nullable();
            $t->string('avatar', 16)->nullable();
            $t->text('description')->nullable();
            $t->foreignId('batch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('direct_key', 40)->nullable()->unique();     // "12:57" for 1:1 rooms
            $t->string('invite_code', 16)->nullable()->unique();
            $t->boolean('invite_enabled')->default(true);
            $t->enum('join_mode', ['open', 'approval', 'invite_only'])->default('approval');
            // who_can_send: all|admins ; send_media, send_links, members_can_invite: bool ; slow_mode_sec: int ; voice_enabled: bool
            $t->json('settings');
            $t->unsignedBigInteger('last_message_id')->nullable();
            $t->timestamp('last_message_at')->nullable()->index();
            $t->unsignedInteger('members_count')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('chat_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['owner', 'admin', 'moderator', 'member'])->default('member');
            $t->boolean('can_send')->nullable();              // per-member override
            $t->timestamp('muted_until')->nullable();         // moderation mute
            $t->boolean('notifications')->default(true);      // user muted notifications
            $t->boolean('pinned')->default(false);
            $t->unsignedBigInteger('last_read_message_id')->nullable();
            $t->timestamp('joined_at')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
            $t->timestamp('left_at')->nullable();
            $t->timestamps();
            $t->unique(['room_id', 'user_id']);
            $t->index(['user_id', 'left_at']);
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('type', ['text', 'image', 'file', 'audio', 'poll', 'system'])->default('text');
            $t->text('body')->nullable();
            $t->json('meta')->nullable();          // file url, size, poll options, mentions
            $t->foreignId('reply_to_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $t->string('client_id', 40)->nullable();   // de-dupe retries from the client
            $t->timestamp('edited_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['room_id', 'id']);
        });

        Schema::create('chat_join_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['room_id', 'user_id']);
        });

        Schema::create('chat_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('reason');
            $t->enum('status', ['open', 'actioned', 'dismissed'])->default('open');
            $t->timestamps();
        });

        // Who may start a direct chat with whom (editable matrix in admin)
        Schema::create('chat_policies', function (Blueprint $t) {
            $t->id();
            $t->string('from_role', 30);
            $t->string('to_role', 30);
            $t->enum('rule', ['allow', 'deny', 'shared_batch', 'support_only'])->default('allow');
            $t->timestamps();
            $t->unique(['from_role', 'to_role']);
        });

        // Future: voice / video rooms (1:1, 1:n, n:n) via SFU
        Schema::create('call_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('room_id')->nullable()->constrained('chat_rooms')->nullOnDelete();
            $t->enum('type', ['voice', 'video', 'live']);
            $t->enum('mode', ['one_to_one', 'one_to_many', 'many_to_many']);
            $t->string('sfu_room_id')->nullable();
            $t->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('started_at')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
            $t->timestamp('ended_at')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
            $t->timestamps();
        });

        Schema::create('call_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('call_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['host', 'speaker', 'listener'])->default('listener');
            $t->timestamp('joined_at')->default(DB::raw('CURRENT_TIMESTAMP'))->nullable();
            $t->timestamp('left_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['call_participants', 'call_sessions', 'chat_policies', 'chat_reports', 'chat_join_requests', 'chat_messages', 'chat_members', 'chat_rooms'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
