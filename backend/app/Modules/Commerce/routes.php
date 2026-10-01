<?php

use App\Modules\Commerce\Http\Controllers\Admin\CouponController;
use App\Modules\Commerce\Http\Controllers\Admin\EnrollmentController;
use App\Modules\Commerce\Http\Controllers\Admin\PaymentController;
use App\Modules\Commerce\Http\Controllers\Admin\RevenueShareController;
use App\Modules\Commerce\Http\Controllers\Admin\SwapController;
use App\Modules\Commerce\Http\Controllers\App\CheckoutController;
use App\Modules\Commerce\Http\Controllers\PublicInvoiceController;
use App\Modules\Commerce\Http\Controllers\Webhooks\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/razorpay', [WebhookController::class, 'razorpay']);
Route::post('webhooks/phonepe', [WebhookController::class, 'phonepe']);
Route::get('public/invoices/{token}', [PublicInvoiceController::class, 'show']);

Route::prefix('app')->middleware(['auth:api', 'active'])->group(function () {
    Route::post('checkout/quote', [CheckoutController::class, 'quote']);
    Route::post('checkout', [CheckoutController::class, 'checkout'])->middleware('throttle:10,1');
    Route::post('orders/{order:order_no}/verify', [CheckoutController::class, 'verify']);
    Route::get('orders/{order:order_no}/status', [CheckoutController::class, 'status']);
    Route::get('orders', [CheckoutController::class, 'myOrders']);
});

Route::prefix('admin')->middleware(['auth:api', 'active', 'staff'])->group(function () {
    Route::middleware('permission:payments.view')->group(function () {
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/{order}', [PaymentController::class, 'show']);
        Route::post('payments/{order}/refresh', [PaymentController::class, 'refresh']);
        Route::get('payments/{order}/whatsapp', [PaymentController::class, 'linkWhatsapp']);
    });
    Route::get('payments-export', [PaymentController::class, 'export'])->middleware('permission:payments.export');
    Route::post('payment-links', [PaymentController::class, 'createLink'])->middleware('permission:payments.create_link');
    Route::post('admissions', [PaymentController::class, 'manualAdmission'])->middleware('permission:enrollments.add_manual');
    Route::post('payments/{order}/refund', [PaymentController::class, 'refund'])->middleware('permission:payments.refund');
    Route::post('payments/{order}/invoice', [PaymentController::class, 'invoice'])->middleware('permission:invoices.send');
    Route::post('payments/{order}/whatsapp/link', [PaymentController::class, 'sendLinkWhatsapp'])->middleware(['permission:payments.create_link', 'throttle:20,1']);
    Route::post('payments/{order}/whatsapp/invoice', [PaymentController::class, 'sendInvoiceWhatsapp'])->middleware(['permission:invoices.send', 'throttle:20,1']);

    // course swap (Admissions → Course swap)
    Route::middleware('permission:enrollments.swap')->group(function () {
        Route::get('swaps', [SwapController::class, 'index']);
        Route::get('swaps/lookup', [SwapController::class, 'lookup']);
        Route::get('enrollments/{enrollment}/swap-quote', [SwapController::class, 'quote']);
        Route::post('enrollments/{enrollment}/swap', [SwapController::class, 'store']);
        Route::post('swaps/{swap}/cancel', [SwapController::class, 'cancel']);
    });

    Route::get('batches/{batch}/students', [EnrollmentController::class, 'index'])->middleware('permission:enrollments.view');
    Route::post('enrollments/{enrollment}/remove', [EnrollmentController::class, 'remove']);
    Route::post('enrollments/{enrollment}/extend', [EnrollmentController::class, 'extend']);
    Route::post('enrollments/{enrollment}/move', [EnrollmentController::class, 'move']);

    Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:coupons.view');
    Route::middleware('permission:coupons.manage')->group(function () {
        Route::post('coupons', [CouponController::class, 'store']);
        Route::patch('coupons/{coupon}', [CouponController::class, 'update']);
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);
    });

    Route::middleware('permission:revenue_share.view')->group(function () {
        Route::get('revenue-share', [RevenueShareController::class, 'summary']);
        Route::put('revenue-share', [RevenueShareController::class, 'configure']);
        Route::get('revenue-share/ledger', [RevenueShareController::class, 'ledger']);
        Route::get('revenue-share/payouts', [RevenueShareController::class, 'payouts']);
        Route::post('revenue-share/payouts', [RevenueShareController::class, 'storePayout']);
    });
});
