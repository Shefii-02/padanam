<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->foreignId('exam_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('course_type', ['live_recorded', 'live_only', 'recorded_only', 'tests_only', 'material_only', 'custom'])->default('live_recorded');
            $t->string('language', 20)->default('ml');
            $t->string('thumbnail')->nullable();
            $t->string('intro_video_url')->nullable();
            $t->text('short_description')->nullable();
            $t->longText('description')->nullable();
            $t->json('what_you_get')->nullable();
            $t->string('level')->nullable();
            // feature switches decide which tabs students see
            $t->json('features');
            $t->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $t->boolean('is_featured')->default(false);
            $t->unsignedInteger('sort')->default(0);
            $t->decimal('rating', 2, 1)->default(0);
            $t->unsignedInteger('students_count')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('course_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['manager', 'teacher'])->default('teacher');
            $t->timestamps();
            $t->unique(['course_id', 'user_id']);
        });

        Schema::create('batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('code', 20)->unique();
            $t->boolean('is_default')->default(false);
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->unsignedInteger('price')->default(0);          // paise
            $t->unsignedInteger('mrp')->default(0);            // paise
            $t->boolean('is_free')->default(false);
            $t->enum('validity_type', ['fixed_date', 'days', 'lifetime'])->default('days');
            $t->unsignedSmallInteger('validity_days')->nullable();
            $t->date('valid_until')->nullable();
            $t->unsignedInteger('seat_limit')->nullable();
            $t->unsignedInteger('seats_taken')->default(0);
            $t->boolean('enrollment_open')->default(true);
            $t->enum('coupon_policy', ['allow', 'deny', 'custom'])->default('allow');
            $t->unsignedInteger('sort')->default(0);
            $t->enum('status', ['active', 'closed', 'archived'])->default('active');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('batch_staff', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['manager', 'teacher'])->default('teacher');
            $t->timestamps();
            $t->unique(['batch_id', 'user_id']);
        });

        Schema::create('course_folders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('course_folders')->cascadeOnDelete();
            $t->string('title');
            $t->unsignedInteger('sort')->default(0);
            $t->json('batch_ids')->nullable();                 // null = visible to all batches
            $t->timestamp('unlock_at')->nullable();
            $t->timestamps();
        });

        Schema::create('videos', function (Blueprint $t) {
            $t->id();
            $t->enum('source', ['youtube', 'aws'])->default('youtube');
            $t->string('youtube_id')->nullable();
            $t->string('url')->nullable();
            $t->string('s3_key')->nullable();
            $t->string('hls_path')->nullable();
            $t->unsignedInteger('duration_sec')->default(0);
            $t->string('thumbnail')->nullable();
            $t->timestamps();
        });

        Schema::create('materials', function (Blueprint $t) {
            $t->id();
            $t->string('file_path');
            $t->string('mime', 60)->nullable();
            $t->unsignedInteger('pages')->nullable();
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->boolean('downloadable')->default(true);
            $t->timestamps();
        });

        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->json('title')->nullable();           // {en, ml}
            $t->json('body');                        // {en: html, ml: html}
            $t->json('tip')->nullable();
            $t->json('one_liners')->nullable();
            $t->timestamps();
        });

        Schema::create('external_links', function (Blueprint $t) {
            $t->id();
            $t->string('url');
            $t->string('label')->nullable();
            $t->timestamps();
        });

        Schema::create('contents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('folder_id')->nullable()->constrained('course_folders')->cascadeOnDelete();  // null = outside folders
            $t->enum('type', ['video', 'live', 'pdf', 'note', 'article', 'test', 'quiz', 'link']);
            $t->string('title');
            $t->text('description')->nullable();
            $t->enum('access', ['free', 'premium', 'demo'])->default('premium')->index();
            $t->unsignedInteger('sort')->default(0);
            $t->timestamp('publish_at')->nullable();
            $t->foreignId('unlock_after_content_id')->nullable()->constrained('contents')->nullOnDelete();
            $t->json('batch_ids')->nullable();
            $t->nullableMorphs('contentable');     // Video, LiveClass, Material, Note, Article, Test, ExternalLink
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['course_id', 'folder_id', 'sort']);
        });

        Schema::create('content_progress', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('content_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('progress')->default(0);
            $t->unsignedInteger('last_position')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'content_id']);
        });

        Schema::create('live_classes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('folder_id')->nullable()->constrained('course_folders')->nullOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->timestamp('starts_at')->index();
            $t->unsignedSmallInteger('duration_min')->default(60);
            $t->enum('source', ['youtube', 'meet_youtube', 'aws'])->default('meet_youtube');
            $t->string('stream_url')->nullable();
            $t->string('youtube_id')->nullable();
            $t->string('meet_url')->nullable();
            $t->enum('status', ['scheduled', 'live', 'ended', 'cancelled'])->default('scheduled')->index();
            $t->boolean('save_recording')->default(true);
            $t->foreignId('recording_content_id')->nullable()->constrained('contents')->nullOnDelete();
            $t->timestamp('alert_sent_at')->nullable();
            $t->timestamp('went_live_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('live_class_attendance', function (Blueprint $t) {
            $t->id();
            $t->foreignId('live_class_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('joined_at');
            $t->unsignedInteger('watched_seconds')->default(0);
            $t->unique(['live_class_id', 'user_id']);
        });

        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('cover')->nullable();
            $t->longText('body');
            $t->foreignId('exam_category_id')->nullable()->constrained()->nullOnDelete();
            $t->json('tags')->nullable();
            $t->unsignedSmallInteger('reading_min')->default(3);
            $t->enum('access', ['free', 'premium'])->default('free');
            $t->enum('status', ['draft', 'scheduled', 'published'])->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['articles', 'live_class_attendance', 'live_classes', 'content_progress', 'contents', 'external_links', 'notes', 'materials', 'videos', 'course_folders', 'batch_staff', 'batches', 'course_staff', 'courses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
