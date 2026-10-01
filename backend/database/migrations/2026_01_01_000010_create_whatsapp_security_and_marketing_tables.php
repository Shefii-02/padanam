<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** WhatsApp API connections. "share" = payment links / invoices, "otp" = login OTP. Token is stored encrypted. */
        Schema::create('whatsapp_accounts', function (Blueprint $t) {
            $t->id();
            $t->enum('purpose', ['share', 'otp'])->unique();
            $t->string('provider', 30)->default('generic');
            $t->boolean('enabled')->default(false);
            $t->string('api_url', 500)->nullable();          // text message endpoint
            $t->string('document_url', 500)->nullable();     // file endpoint (empty = same as api_url)
            $t->text('token')->nullable();                   // encrypted
            $t->enum('auth_type', ['bearer', 'header', 'query', 'body'])->default('bearer');
            $t->string('auth_key', 60)->default('token');    // header / query / body field name
            $t->enum('body_format', ['json', 'form'])->default('json');
            $t->json('text_body')->nullable();               // template with {{phone}} {{message}} …
            $t->json('document_body')->nullable();           // template with {{file_url}} {{file_name}} {{caption}}
            $t->string('success_path', 100)->nullable();     // optional JSON path that must be truthy
            $t->string('country_code', 4)->default('91');
            $t->text('message_template')->nullable();        // OTP text
            $t->boolean('fallback_sms')->default(true);      // OTP: use SMS when WhatsApp fails
            $t->timestamp('last_tested_at')->nullable();
            $t->boolean('last_test_ok')->nullable();
            $t->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $t) {
            $t->id();
            $t->enum('account', ['share', 'otp']);
            $t->string('to_phone', 15)->index();
            $t->enum('purpose', ['otp', 'payment_link', 'invoice', 'admission', 'swap', 'test', 'manual'])->index();
            $t->enum('type', ['text', 'document'])->default('text');
            $t->text('body')->nullable();
            $t->string('file_url', 500)->nullable();
            $t->string('file_name')->nullable();
            $t->enum('status', ['queued', 'sent', 'failed'])->default('queued')->index();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->text('response')->nullable();
            $t->string('error', 500)->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
        });

        Schema::create('otp_logs', function (Blueprint $t) {
            $t->id();
            $t->string('phone', 15)->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('channel', ['whatsapp', 'sms', 'log'])->default('sms');
            $t->enum('status', ['sent', 'failed', 'verified', 'wrong_code', 'expired', 'rate_limited', 'blocked'])->index();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->string('ip', 45)->nullable();
            $t->string('platform', 20)->nullable();
            $t->string('user_agent')->nullable();
            $t->string('error', 300)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->index('created_at');
        });

        /** Move a student to another batch of ANY course, with or without collecting the price difference. */
        Schema::create('course_swaps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('from_enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $t->foreignId('from_batch_id')->constrained('batches')->cascadeOnDelete();
            $t->foreignId('to_batch_id')->constrained('batches')->cascadeOnDelete();
            $t->unsignedInteger('price_from')->default(0);
            $t->unsignedInteger('price_to')->default(0);
            $t->integer('difference')->default(0);            // paise, can be negative
            $t->unsignedInteger('collected')->default(0);     // paise
            $t->enum('mode', ['free', 'collected', 'payment_link'])->default('free');
            $t->enum('status', ['pending_payment', 'done', 'cancelled'])->default('done')->index();
            $t->boolean('keep_expiry')->default(true);
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('marketing_exports', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30);
            $t->string('format', 20)->default('standard');
            $t->json('filters')->nullable();
            $t->json('fields')->nullable();
            $t->unsignedInteger('rows')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['marketing_exports', 'course_swaps', 'otp_logs', 'whatsapp_messages', 'whatsapp_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

