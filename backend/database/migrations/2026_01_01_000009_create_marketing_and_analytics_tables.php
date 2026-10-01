<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name')->nullable();
            $t->string('phone', 15)->index();
            $t->string('email')->nullable();
            $t->string('district')->nullable();
            $t->enum('source', ['demo_video', 'launch_offer', 'course_page', 'app_install', 'free_test', 'whatsapp', 'manual', 'website'])->index();
            $t->foreignId('interested_category_id')->nullable()->constrained('exam_categories')->nullOnDelete();
            $t->foreignId('interested_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $t->enum('status', ['new', 'contacted', 'converted', 'lost'])->default('new')->index();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->unsignedSmallInteger('score')->default(0);       // interest score (visits, demo watch …)
            $t->timestamp('last_contacted_at')->nullable();
            $t->timestamp('converted_at')->nullable();
            $t->timestamps();
            $t->unique(['phone', 'source', 'interested_course_id']);
        });

        Schema::create('lead_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['call', 'whatsapp', 'note', 'payment_link', 'status_change', 'auto']);
            $t->text('note')->nullable();
            $t->foreignId('by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('activity_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event', 60)->index();        // app_open, video_play, test_start, course_view, demo_play, offer_click …
            $t->json('properties')->nullable();
            $t->string('platform', 20)->nullable();
            $t->timestamp('at')->useCurrent()->index();
        });

        Schema::create('daily_user_stats', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('minutes')->default(0);
            $t->unsignedSmallInteger('videos')->default(0);
            $t->unsignedSmallInteger('tests')->default(0);
            $t->unsignedSmallInteger('quiz')->default(0);
            $t->boolean('active')->default(false);
            $t->unique(['date', 'user_id']);
        });

        Schema::create('doubts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $t->string('subject')->nullable();
            $t->text('text');
            $t->string('image')->nullable();
            $t->text('answer')->nullable();
            $t->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('answered_at')->nullable();
            $t->unsignedInteger('likes')->default(0);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['doubts', 'daily_user_stats', 'activity_events', 'lead_activities', 'leads'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
