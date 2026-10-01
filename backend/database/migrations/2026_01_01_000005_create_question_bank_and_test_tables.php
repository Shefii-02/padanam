<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_folders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('question_folders')->cascadeOnDelete();
            $t->string('name');
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('labels', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('color', 9)->default('#3B4FD8');
            $t->timestamps();
        });

        Schema::create('questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('folder_id')->nullable()->constrained('question_folders')->nullOnDelete();
            $t->enum('type', ['mcq_single', 'mcq_multi', 'true_false', 'numeric', 'match', 'passage'])->default('mcq_single');
            $t->enum('difficulty', ['easy', 'moderate', 'hard'])->default('moderate')->index();
            $t->string('subject')->nullable()->index();
            $t->string('topic')->nullable();
            $t->decimal('default_marks', 5, 2)->default(1);
            $t->decimal('default_negative', 5, 2)->default(0);
            $t->string('numeric_answer')->nullable();
            $t->string('source')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->foreignId('passage_id')->nullable()->constrained('questions')->nullOnDelete();
            $t->foreignId('import_id')->nullable();
            $t->string('hash', 64)->nullable()->index();           // duplicate detection
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('question_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->string('lang', 8);
            $t->longText('text');            // html, may include <img>
            $t->longText('solution')->nullable();
            $t->timestamps();
            $t->unique(['question_id', 'lang']);
        });

        Schema::create('question_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('sort')->default(0);
            $t->boolean('is_correct')->default(false);
            $t->timestamps();
        });

        Schema::create('question_option_translations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('option_id')->constrained('question_options')->cascadeOnDelete();
            $t->string('lang', 8);
            $t->text('text');
            $t->timestamps();
            $t->unique(['option_id', 'lang']);
        });

        Schema::create('question_label', function (Blueprint $t) {
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->foreignId('label_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->primary(['question_id', 'label_id']);
        });

        Schema::create('question_imports', function (Blueprint $t) {
            $t->id();
            $t->string('file_path');
            $t->enum('format', ['csv', 'docx']);
            $t->string('lang', 8)->default('en');
            $t->foreignId('folder_id')->nullable()->constrained('question_folders')->nullOnDelete();
            $t->json('label_ids')->nullable();
            $t->decimal('default_marks', 5, 2)->default(1);
            $t->decimal('default_negative', 5, 2)->default(0);
            $t->boolean('match_translations')->default(false);
            $t->enum('status', ['uploaded', 'parsed', 'importing', 'done', 'failed'])->default('uploaded');
            $t->unsignedInteger('total')->default(0);
            $t->unsignedInteger('ready')->default(0);
            $t->unsignedInteger('duplicates')->default(0);
            $t->unsignedInteger('imported')->default(0);
            $t->unsignedInteger('failed')->default(0);
            $t->json('preview')->nullable();
            $t->string('error_report_path')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('question_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('reason');
            $t->text('note')->nullable();
            $t->enum('status', ['open', 'fixed', 'rejected'])->default('open');
            $t->timestamps();
        });

        Schema::create('tests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->nullable()->constrained()->nullOnDelete();   // null = standalone
            $t->foreignId('exam_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->text('instructions')->nullable();
            $t->enum('mode', ['online', 'omr'])->default('online');
            $t->enum('kind', ['mock', 'sectional', 'chapter', 'pyq', 'daily_quiz', 'practice'])->default('mock');
            $t->enum('access', ['free', 'premium'])->default('premium');
            $t->json('languages')->nullable();
            $t->decimal('total_marks', 8, 2)->default(0);
            $t->unsignedSmallInteger('total_questions')->default(0);
            $t->unsignedSmallInteger('duration_min')->default(60);
            $t->boolean('sectional_timing')->default(false);
            $t->boolean('shuffle')->default(false);
            $t->enum('show_result', ['instant', 'after_end', 'manual'])->default('instant');
            $t->unsignedTinyInteger('attempts_allowed')->default(1);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamp('result_published_at')->nullable();
            $t->enum('status', ['draft', 'published', 'archived'])->default('draft')->index();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('test_sections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('short_name', 20)->nullable();
            $t->unsignedTinyInteger('sort')->default(0);
            $t->unsignedSmallInteger('duration_min')->nullable();
            $t->decimal('marks_per_question', 5, 2)->default(1);
            $t->decimal('negative_per_question', 5, 2)->default(0);
            $t->boolean('en_only')->default(false);
            $t->timestamps();
        });

        Schema::create('test_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->foreignId('section_id')->nullable()->constrained('test_sections')->cascadeOnDelete();
            $t->foreignId('question_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->decimal('marks', 5, 2)->nullable();       // override
            $t->decimal('negative', 5, 2)->nullable();    // override
            $t->boolean('en_only')->default(false);
            $t->timestamps();
            $t->index(['test_id', 'section_id']);
            $t->index(['test_id', 'question_id']);
            $t->index(['test_id', 'sort']);
            $t->index(['test_id', 'marks']);
            $t->index(['test_id', 'negative']);
            $t->index(['test_id', 'en_only']);
            $t->index(['section_id', 'sort']);
            $t->index(['section_id', 'marks']);
            $t->index(['section_id', 'negative']);
            $t->index(['section_id', 'en_only']);
            $t->index(['question_id', 'sort']);
            $t->index(['question_id', 'marks']);
            $t->index(['question_id', 'negative']);
            $t->index(['question_id', 'en_only']);
            $t->unique(['test_id', 'section_id', 'sort']);
            $t->unique(['test_id', 'section_id', 'question_id']);

        });

        Schema::create('attempts', function (Blueprint $t) {
            $t->id();
            $t->ulid('uid')->unique();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('status', ['in_progress', 'submitted', 'evaluated'])->default('in_progress')->index();
            $t->string('language', 8)->default('en');
            $t->unsignedTinyInteger('current_section')->default(0);
            $t->json('section_time')->nullable();
            $t->decimal('score', 8, 2)->nullable();
            $t->decimal('negative', 8, 2)->nullable();
            $t->unsignedSmallInteger('correct')->default(0);
            $t->unsignedSmallInteger('wrong')->default(0);
            $t->unsignedSmallInteger('skipped')->default(0);
            $t->unsignedInteger('time_spent')->default(0);
            $t->unsignedInteger('rank')->nullable();
            $t->decimal('percentile', 5, 2)->nullable();
            $t->timestamp('started_at');
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
            $t->index(['test_id', 'score']);
        });

        Schema::create('attempt_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('test_question_id')->constrained()->cascadeOnDelete();
            $t->json('selected')->nullable();           // option ids / numeric
            $t->boolean('is_marked')->default(false);
            $t->boolean('visited')->default(false);
            $t->unsignedInteger('time_spent')->default(0);
            $t->boolean('is_correct')->nullable();
            $t->decimal('marks', 5, 2)->default(0);
            $t->unique(['attempt_id', 'test_question_id']);
            $t->timestamps();   
        });

        Schema::create('omr_sheets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('test_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('attempt_id')->nullable()->constrained()->nullOnDelete();
            $t->string('image_path')->nullable();
            $t->json('parsed_answers')->nullable();
            $t->enum('status', ['uploaded', 'needs_review', 'evaluated'])->default('uploaded');
            $t->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('daily_quizzes', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('exam_category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('test_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('label_id')->nullable()->constrained()->nullOnDelete();   // auto-pick source
            $t->string('topic')->nullable();
            $t->unsignedTinyInteger('questions')->default(10);
            $t->enum('status', ['planned', 'ready', 'published'])->default('planned');
            $t->timestamps();
            $t->unique(['date', 'exam_category_id']);
        });

        Schema::create('study_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('batch_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();   // personal plan
            $t->string('title');
            $t->boolean('is_template')->default(false);
            $t->json('week')->nullable();      // template: [{day, items:[{type, content_id, minutes, title}]}]
            $t->timestamps();
        });

        Schema::create('study_plan_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_plan_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date')->index();
            $t->string('title');
            $t->string('type', 20);
            $t->foreignId('content_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('minutes')->default(30);
            $t->timestamp('done_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['study_plan_tasks', 'study_plans', 'daily_quizzes', 'omr_sheets', 'attempt_answers', 'attempts', 'test_questions', 'test_sections', 'tests', 'question_reports', 'question_imports', 'question_label', 'question_option_translations', 'question_options', 'question_translations', 'questions', 'labels', 'question_folders'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
