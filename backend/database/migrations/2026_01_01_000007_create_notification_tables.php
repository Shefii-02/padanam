<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $t) {
            $t->id();
            $t->string('key', 40)->unique();      // class_alert, live_now, course_update, payment, test_result, chat, announcement, offer, reminder
            $t->string('name');
            $t->string('description')->nullable();
            $t->enum('importance', ['min', 'low', 'default', 'high', 'max'])->default('default');
            $t->string('sound')->nullable();       // e.g. class_ring
            $t->boolean('user_can_disable')->default(true);
            $t->timestamps();
        });

        Schema::create('notification_templates', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();           // live.alert, payment.success …
            $t->string('channel_key', 40);
            $t->string('title');
            $t->text('body');
            $t->string('deep_link')->nullable();
            $t->json('variables')->nullable();
            $t->timestamps();
        });

        Schema::create('notification_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->string('image')->nullable();
            $t->string('deep_link')->nullable();
            $t->string('channel_key', 40);
            $t->enum('audience', ['all_installs', 'all_users', 'course', 'batch', 'category_interest', 'role', 'custom_users', 'inactive_days', 'leads', 'expiring']);
            $t->json('audience_filter')->nullable();
            $t->timestamp('scheduled_at')->nullable()->index();
            $t->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled'])->default('draft');
            $t->unsignedInteger('target_count')->default(0);
            $t->unsignedInteger('sent')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->unsignedInteger('opened')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('notifications', function (Blueprint $t) {    // in-app inbox
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_id')->nullable()->constrained('notification_campaigns')->nullOnDelete();
            $t->string('channel_key', 40);
            $t->string('title');
            $t->text('body');
            $t->string('icon', 16)->nullable();
            $t->string('deep_link')->nullable();
            $t->json('data')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('opened_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at']);
        });

        Schema::create('notification_preferences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('channel_key', 40);
            $t->boolean('enabled')->default(true);
            $t->timestamps();
            $t->unique(['user_id', 'channel_key']);
        });
    }

    public function down(): void
    {
        foreach (['notification_preferences', 'notifications', 'notification_campaigns', 'notification_templates', 'notification_channels'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
