<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('title');
            $t->enum('type', ['percent', 'flat']);
            $t->unsignedInteger('value');                    // percent (0-100) or paise
            $t->unsignedInteger('max_discount')->nullable(); // paise
            $t->unsignedInteger('min_amount')->default(0);   // paise
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('total_limit')->nullable();
            $t->unsignedSmallInteger('per_user_limit')->default(1);
            $t->unsignedInteger('used_count')->default(0);
            $t->enum('audience', ['all', 'new_users', 'existing_students', 'specific_users'])->default('all');
            $t->boolean('is_active')->default(true);
            $t->boolean('show_in_app')->default(false);       // list on checkout as an offer
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('coupon_batches', function (Blueprint $t) {        // where it applies (empty = all)
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $t->primary(['coupon_id', 'batch_id']);
        });

        Schema::create('coupon_eligibility', function (Blueprint $t) {    // must already own one of these
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('required_batch_id')->constrained('batches')->cascadeOnDelete();
            $t->primary(['coupon_id', 'required_batch_id']);
        });

        Schema::create('coupon_users', function (Blueprint $t) {
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['coupon_id', 'user_id']);
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_no', 20)->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('phone', 15)->nullable()->index();
            $t->string('name')->nullable();
            $t->foreignId('batch_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('amount');          // batch price at order time
            $t->unsignedInteger('discount')->default(0);
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('tax')->default(0);
            $t->unsignedInteger('total');
            $t->enum('gateway', ['razorpay', 'phonepe', 'manual', 'free']);
            $t->enum('channel', ['app', 'web', 'payment_link', 'admin'])->default('app');
            $t->enum('status', ['created', 'pending', 'paid', 'failed', 'refunded', 'expired', 'cancelled'])->default('created')->index();
            $t->string('gateway_order_id')->nullable()->index();
            $t->string('payment_link_url')->nullable();
            $t->string('payment_link_id')->nullable();
            $t->timestamp('link_expires_at')->nullable();
            $t->string('manual_mode')->nullable();       // cash, bank, upi_outside, scholarship
            $t->string('manual_reference')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->enum('gateway', ['razorpay', 'phonepe', 'manual', 'free']);
            $t->string('gateway_payment_id')->nullable()->unique();   // idempotency
            $t->unsignedInteger('amount');
            $t->enum('status', ['captured', 'failed', 'refunded'])->index();
            $t->string('method')->nullable();       // upi, card, netbanking, wallet
            $t->json('raw')->nullable();
            $t->timestamp('paid_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('amount');
            $t->string('reason')->nullable();
            $t->string('gateway_refund_id')->nullable();
            $t->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('webhook_logs', function (Blueprint $t) {
            $t->id();
            $t->string('gateway', 20);
            $t->string('event')->nullable();
            $t->string('event_id')->nullable()->unique();
            $t->json('payload');
            $t->boolean('signature_ok')->default(false);
            $t->timestamp('processed_at')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('discount');
            $t->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();   // denormalised for fast access checks
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('source', ['purchase', 'manual', 'free', 'coupon_100'])->default('purchase');
            $t->timestamp('starts_at');
            $t->timestamp('expires_at')->nullable()->index();
            $t->enum('status', ['active', 'expired', 'revoked'])->default('active')->index();
            $t->boolean('class_alerts')->default(true);       // per-course class alert on/off
            $t->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'batch_id']);
            $t->index(['user_id', 'course_id', 'status']);
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_no', 30)->unique();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('subtotal');
            $t->unsignedInteger('tax');
            $t->unsignedInteger('total');
            $t->json('seller');                // name, gstin, address at the time
            $t->json('buyer');
            $t->string('pdf_path')->nullable();
            $t->string('public_token', 40)->unique();    // shareable link /inv/{token}
            $t->json('sent_via')->nullable();
            $t->timestamps();
        });

        // Revenue share (off by default)
        Schema::create('revenue_share_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('order_total');
            $t->decimal('percent', 5, 2);
            $t->unsignedInteger('share_amount');
            $t->timestamps();
        });

        Schema::create('payouts', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('amount');
            $t->date('paid_on');
            $t->string('method', 20);
            $t->string('reference')->nullable();
            $t->text('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payouts', 'revenue_share_ledger', 'invoices', 'enrollments', 'coupon_redemptions', 'webhook_logs', 'refunds', 'payments', 'orders', 'coupon_users', 'coupon_eligibility', 'coupon_batches', 'coupons'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
