<?php

namespace App\Modules\Commerce\Services;

use App\Core\Enums\Role;
use App\Core\Support\Audit;
use App\Core\Support\Code;
use App\Core\Support\DomainException;
use App\Core\Support\Money;
use App\Models\Batch;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Modules\Commerce\Gateways\PhonePeGateway;
use App\Modules\Commerce\Gateways\RazorpayGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private CouponService $coupons,
        private EnrollmentService $enrollments,
        private InvoiceService $invoices,
        private RevenueShareService $revenue,
        private RazorpayGateway $razorpay,
        private PhonePeGateway $phonepe,
    ) {}

    /** Price breakdown for the checkout screen. */
    public function quote(Batch $batch, ?User $user, ?string $code): array
    {
        $this->assertBuyable($batch, $user);
        $amount = $batch->price;
        $discount = 0;
        $coupon = null;
        $couponError = null;
        if ($code) {
            try {
                ['coupon' => $coupon, 'discount' => $discount] = $this->coupons->apply($code, $batch, $user, $amount);
            } catch (DomainException $e) {
                $couponError = $e->getMessage();
            }
        }
        $total = max(0, $amount - $discount);

        return [
            'batch_id' => $batch->id,
            'mrp' => Money::format($batch->mrp),
            'price' => Money::format($amount),
            'discount' => Money::format($discount),
            'total' => Money::format($total),
            'total_paise' => $total,
            'coupon' => $coupon ? ['code' => $coupon->code, 'title' => $coupon->title] : null,
            'coupon_error' => $couponError,
            'offers' => $this->coupons->offersFor($batch, $user),
            'validity' => $batch->validity_type === 'lifetime' ? 'Lifetime' : 'Until '.$batch->expiryFor()?->format('j M Y'),
            'gateways' => array_values(array_filter([
                config('services.razorpay.key') ? 'razorpay' : null,
                config('services.phonepe.merchant_id') ? 'phonepe' : null,
            ])),
        ];
    }

    /** App checkout: creates the order and opens the gateway (or enrolls at once if the total is ₹0). */
    public function checkout(User $user, Batch $batch, ?string $code, string $gateway): array
    {
        $this->assertBuyable($batch, $user);
        $order = $this->makeOrder($user, $batch, $code, $gateway, 'app');

        if ($order->total === 0) {
            $this->markPaid($order, 'free', null, null, []);

            return ['order_no' => $order->order_no, 'status' => 'paid', 'gateway' => 'free'];
        }
        $gw = $gateway === 'phonepe' ? $this->phonepe : $this->razorpay;

        return ['order_no' => $order->order_no, 'status' => 'pending'] + $gw->checkout($order->load('batch.course', 'user'));
    }

    /** Razorpay: the app sends the signature after payment. */
    public function verifyRazorpay(Order $order, string $rzOrderId, string $paymentId, string $signature): Order
    {
        if ($order->gateway_order_id !== $rzOrderId || ! $this->razorpay->verifySignature($rzOrderId, $paymentId, $signature)) {
            Log::channel('payments')->warning('Bad signature', ['order' => $order->order_no]);
            throw new DomainException('Payment could not be verified. If money was deducted, it will be confirmed automatically in a few minutes.');
        }

        return $this->markPaid($order, 'razorpay', $paymentId, null, ['verified_by' => 'signature']);
    }

    /** Polling (PhonePe return, or app reopening after a crash). */
    public function refreshStatus(Order $order): Order
    {
        if ($order->status === 'paid' || ! in_array($order->gateway, ['razorpay', 'phonepe'], true)) {
            return $order;
        }
        $st = ($order->gateway === 'phonepe' ? $this->phonepe : $this->razorpay)->status($order);
        if ($st['paid']) {
            return $this->markPaid($order, $order->gateway, $st['payment_id'], $st['method'], $st['raw']);
        }

        return $order;
    }

    /**
     * Idempotent – webhooks, app verify and polling may all arrive; only the first one counts.
     * Paid order → payment row → coupon redemption → enrollment → invoice → revenue share.
     */
    public function markPaid(Order $order, string $gateway, ?string $paymentId, ?string $method, array $raw, ?int $amount = null): Order
    {
        $justPaid = false;
        $order = DB::transaction(function () use ($order, $gateway, $paymentId, $method, $raw, $amount, &$justPaid) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($order->status === 'paid') {
                return $order;
            }
            if ($paymentId && Payment::where('gateway_payment_id', $paymentId)->exists()) {
                return $order;
            }
            $user = $order->user ?? $this->userForPhone($order->phone, $order->name);
            $order->update(['status' => 'paid', 'paid_at' => now(), 'user_id' => $user->id]);
            $justPaid = true;
            $payment = Payment::create([
                'order_id' => $order->id, 'gateway' => $gateway, 'gateway_payment_id' => $paymentId,
                'amount' => $amount ?? $order->total, 'status' => 'captured', 'method' => $method, 'raw' => $raw, 'paid_at' => now(),
            ]);
            if ($order->coupon_id) {
                CouponRedemption::create(['coupon_id' => $order->coupon_id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount' => $order->discount]);
                Coupon::whereKey($order->coupon_id)->increment('used_count');
            }
            $this->enrollments->grant($user, $order->batch, $order->total === 0 && $order->discount > 0 ? 'coupon_100' : ($gateway === 'manual' ? 'manual' : ($order->total === 0 ? 'free' : 'purchase')), $order, ignoreSeats: $order->channel === 'admin');
            $this->revenue->record($payment);
            app(SwapService::class)->completeForOrder($order);   // finishes a course swap paid by link
            Log::channel('payments')->info('Paid', ['order' => $order->order_no, 'gateway' => $gateway, 'payment' => $paymentId, 'total' => $order->total]);

            return $order;
        });

        if ($order->total > 0) {
            try {
                $this->invoices->forOrder($order);
            } catch (\Throwable $e) {
                Log::channel('payments')->error('Invoice failed '.$order->order_no.': '.$e->getMessage());
            }
        }

        $order = $order->fresh(['invoice', 'batch.course']);
        if ($justPaid && $order->channel !== 'admin') {   // manual admissions choose per request
            dispatch(fn () => app(\App\Modules\WhatsApp\Services\CommerceMessages::class)->afterPaid($order))->afterResponse();
        }

        return $order;
    }

    /** Staff sends a Razorpay link on WhatsApp. When paid, the student is created (if new) and enrolled automatically. */
    public function paymentLink(array $d, User $by): Order
    {
        $batch = Batch::with('course')->findOrFail($d['batch_id']);
        $phone = $d['phone'];
        $existing = User::firstWhere('phone', $phone);
        $this->assertBuyable($batch, $existing, forAdmin: true);
        $order = $this->makeOrder($existing, $batch, $d['coupon'] ?? null, 'razorpay', 'payment_link', $phone, $d['name'] ?? $existing?->name, $by);
        if (isset($d['amount'])) {   // staff can set a custom amount (special offer)
            $order->update(['total' => Money::toPaise($d['amount']), 'discount' => max(0, $order->amount - Money::toPaise($d['amount'])), 'notes' => $d['note'] ?? null]);
        }
        $link = $this->razorpay->paymentLink($order->load('batch.course'), (int) ($d['expires_in_hours'] ?? 72) * 60);
        $order->update(['payment_link_id' => $link['id'], 'payment_link_url' => $link['url'], 'status' => 'pending',
            'link_expires_at' => now()->addHours((int) ($d['expires_in_hours'] ?? 72))]);
        Audit::log('payments.create_link', $order, ['phone' => $phone, 'total' => $order->total]);

        return $order->fresh('batch.course');
    }

    public function whatsappForLink(Order $order): string
    {
        $text = "Hi {$order->name} 👋\nHere is your payment link for *{$order->batch->course->title}* ({$order->batch->name}).\nAmount: *".Money::format($order->total)."*\n{$order->payment_link_url}\nYour course unlocks automatically in the Padanam app after payment.";

        return 'https://wa.me/91'.$order->phone.'?text='.rawurlencode($text);
    }

    /** Manual admission: cash / bank transfer / UPI outside the app / scholarship. */
    public function manualAdmission(array $d, User $by): Order
    {
        $batch = Batch::with('course')->findOrFail($d['batch_id']);
        $user = $this->userForPhone($d['phone'], $d['name'] ?? null);
        $amount = Money::toPaise($d['amount'] ?? 0);

        $order = Order::create([
            'order_no' => Code::orderNo(), 'user_id' => $user->id, 'phone' => $user->phone, 'name' => $user->name,
            'batch_id' => $batch->id, 'amount' => $batch->price, 'discount' => max(0, $batch->price - $amount), 'total' => $amount,
            'gateway' => 'manual', 'channel' => 'admin', 'status' => 'created',
            'manual_mode' => $d['mode'], 'manual_reference' => $d['reference'] ?? null, 'notes' => $d['note'] ?? null, 'created_by' => $by->id,
        ]);
        $order = $this->markPaid($order, 'manual', null, $d['mode'], ['by' => $by->id]);
        if (! empty($d['expires_at'])) {
            $order->enrollment?->update(['expires_at' => \Illuminate\Support\Carbon::parse($d['expires_at'])->endOfDay()]);
        }
        Audit::log('enrollments.add_manual', $order, ['mode' => $d['mode'], 'amount' => $amount]);

        return $order;
    }

    public function refund(Order $order, int $amount, string $reason, bool $revoke): Refund
    {
        $payment = $order->payments()->where('status', 'captured')->latest('id')->firstOrFail();
        $already = (int) Refund::where('payment_id', $payment->id)->where('status', '!=', 'failed')->sum('amount');
        if ($amount <= 0 || $amount + $already > $payment->amount) {
            throw new DomainException('Refund cannot be more than '.Money::format($payment->amount - $already).'.');
        }
        $refund = Refund::create(['payment_id' => $payment->id, 'amount' => $amount, 'reason' => $reason, 'status' => 'pending', 'created_by' => auth()->id()]);
        if ($payment->gateway === 'razorpay' && $payment->gateway_payment_id) {
            $r = $this->razorpay->refund($payment->gateway_payment_id, $amount, ['order_no' => $order->order_no]);
            $refund->update(['gateway_refund_id' => $r['id'], 'status' => 'processed']);
        } else {
            $refund->update(['status' => 'processed']);   // manual / PhonePe dashboard refunds are recorded here
        }
        if ($amount + $already >= $payment->amount) {
            $payment->update(['status' => 'refunded']);
            $order->update(['status' => 'refunded']);
        }
        $this->revenue->onRefund($payment, $amount);
        if ($revoke && $order->enrollment) {
            $this->enrollments->revoke($order->enrollment, 'refund');
        }
        Audit::log('payments.refund', $order, ['amount' => $amount, 'reason' => $reason]);

        return $refund;
    }

    // ------------------------------------------------------------
    private function makeOrder(?User $user, Batch $batch, ?string $code, string $gateway, string $channel, ?string $phone = null, ?string $name = null, ?User $by = null): Order
    {
        $discount = 0;
        $couponId = null;
        if ($code) {
            ['coupon' => $coupon, 'discount' => $discount] = $this->coupons->apply($code, $batch, $user, $batch->price);
            $couponId = $coupon->id;
        }

        return Order::create([
            'order_no' => Code::orderNo(),
            'user_id' => $user?->id,
            'phone' => $phone ?? $user?->phone,
            'name' => $name ?? $user?->name,
            'batch_id' => $batch->id,
            'amount' => $batch->price,
            'discount' => $discount,
            'coupon_id' => $couponId,
            'total' => max(0, $batch->price - $discount),
            'gateway' => $batch->price - $discount <= 0 ? 'free' : $gateway,
            'channel' => $channel,
            'status' => 'created',
            'created_by' => $by?->id,
        ]);
    }

    private function assertBuyable(Batch $batch, ?User $user, bool $forAdmin = false): void
    {
        if (! $forAdmin && ! $batch->isPurchasable()) {
            throw new DomainException($batch->seatsLeft() === 0 ? 'Sorry, this batch is full.' : 'Admissions for this batch are closed.');
        }
        if ($batch->course->status !== 'published' && ! $forAdmin) {
            throw new DomainException('This course is not open yet.');
        }
        if ($user && \App\Models\Enrollment::where('user_id', $user->id)->where('batch_id', $batch->id)->active()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()->addDays(15)))->exists()) {
            throw new DomainException('You already have this batch. Renewal opens 15 days before it ends.');
        }
    }

    private function userForPhone(?string $phone, ?string $name): User
    {
        if (! $phone) {
            throw new DomainException('Mobile number is required.');
        }
        $user = User::firstOrCreate(['phone' => $phone], ['name' => $name, 'is_new_user' => true, 'referral_code' => Code::make(8)]);
        if (! $user->name && $name) {
            $user->update(['name' => $name]);
        }
        if ($user->roles()->doesntExist()) {
            $user->assignRole(Role::Student->value);
        }

        return $user;
    }
}
