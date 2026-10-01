<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('phone', 15)->nullable()->unique();
            $t->string('email')->nullable()->unique();
            $t->string('password')->nullable();                 // staff / admin web login
            $t->string('avatar', 16)->nullable();               // emoji avatar
            $t->string('photo')->nullable();
            $t->enum('gender', ['male', 'female', 'other'])->nullable();
            $t->date('dob')->nullable();
            $t->string('district')->nullable();
            $t->string('state')->default('Kerala');
            $t->string('town')->nullable();
            $t->string('pincode', 10)->nullable();
            $t->string('qualification')->nullable();
            $t->string('language', 8)->default('ml');
            $t->enum('status', ['active', 'blocked'])->default('active');
            $t->boolean('is_new_user')->default(true);
            $t->string('referral_code', 12)->nullable()->unique();
            $t->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_seen_at')->nullable()->index();
            $t->timestamp('profile_completed_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('user_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('target_posts')->nullable();
            $t->string('level')->nullable();
            $t->string('aim')->nullable();
            $t->string('attempt')->nullable();
            $t->unsignedTinyInteger('study_hours')->default(2);
            $t->json('study_days')->nullable();
            $t->string('study_slot')->nullable();
            $t->boolean('reminder')->default(true);
            $t->timestamps();
        });

        Schema::create('staff_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('designation')->nullable();
            $t->json('subjects')->nullable();
            $t->text('bio')->nullable();
            $t->boolean('is_teacher')->default(false);
            $t->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 15)->index();
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });

        Schema::create('devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('platform', ['android', 'ios', 'web', 'windows', 'macos', 'linux']);
            $t->string('fcm_token', 255)->nullable();
            $t->unsignedInteger('app_build')->nullable();
            $t->string('device_name')->nullable();
            $t->timestamp('last_active_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'fcm_token']);
        });

        Schema::create('login_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('ip', 45)->nullable();
            $t->string('platform', 20)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('at')->useCurrent()->index();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value')->nullable();
            $t->timestamps();
        });

        Schema::create('app_versions', function (Blueprint $t) {
            $t->id();
            $t->enum('platform', ['android', 'ios', 'web', 'windows', 'macos', 'linux'])->unique();
            $t->string('latest_version');
            $t->unsignedInteger('latest_build');
            $t->unsignedInteger('min_supported_build');
            $t->text('notes')->nullable();
            $t->string('store_url')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->nullableMorphs('subject');
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
            $t->timestamp('failed_at')->nullable();

        });
        Schema::create('job_batches', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('name');
            $t->integer('total_jobs');
            $t->integer('pending_jobs');
            $t->integer('failed_jobs');
            $t->longText('failed_job_ids');
            $t->mediumText('options')->nullable();
            $t->integer('cancelled_at')->nullable();
            $t->integer('created_at');
            $t->integer('finished_at')->nullable();

        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });
        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->mediumText('value');
            $t->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->string('owner');
            $t->integer('expiration');
        });
    }

    public function down(): void
    {
        foreach (['cache_locks', 'cache', 'failed_jobs', 'job_batches', 'jobs', 'audit_logs', 'app_versions', 'settings', 'password_reset_tokens', 'login_activities', 'devices', 'otp_codes', 'staff_profiles', 'user_profiles', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
