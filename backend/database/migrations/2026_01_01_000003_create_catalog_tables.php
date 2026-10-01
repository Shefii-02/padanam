<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('exam_categories')->nullOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('icon', 16)->nullable();
            $t->string('color', 9)->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_category_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('full_name')->nullable();
            $t->json('eligibility')->nullable();
            $t->json('pattern')->nullable();
            $t->json('posts')->nullable();
            $t->date('next_exam_date')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('user_exam_interests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exam_category_id')->constrained()->cascadeOnDelete();
            $t->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $t->string('target_post')->nullable();
            $t->enum('source', ['setup', 'demo', 'offer', 'course_view', 'manual'])->default('setup');
            $t->timestamps();
            $t->unique(['user_id', 'exam_category_id', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_exam_interests');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_categories');
    }
};
