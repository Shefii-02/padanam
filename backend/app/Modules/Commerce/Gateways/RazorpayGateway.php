<?php

namespace App\Modules\Commerce\Gateways;

use App\Core\Support\DomainException;
use App\Models\Order;
use Razorpay\Api\Api;

class RazorpayGateway implements PaymentGateway
{
    private function api(): Api
    {
        if (! config('services.razorpay.key') || ! config('services.razorpay.secret')) {
            throw new DomainException('Razorpay is not configured yet.', 503);
        }

        return new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    public function checkout(Order $order): array
    {
        $rz = $this->api()->order->create([
            'receipt' => $order->order_no,
            'amount' => $order->total,          // paise
            'currency' => 'INR',
            'notes' => ['order_no' => $order->order_no, 'batch_id' => (string) $order->batch_id],
        ]);
        $order->update(['gateway_order_id' => $rz['id'], 'status' => 'pending']);

        return [
            'gateway' => 'razorpay',
            'key' => config('services.razorpay.key'),
            'order_id' => $rz['id'],
            'amount' => $order->total,
            'currency' => 'INR',
            'name' => config('app.name'),
            'description' => $order->batch->course->title.' – '.$order->batch->name,
            'prefill' => ['name' => $order->name, 'contact' => '+91'.$order->phone, 'email' => $order->user?->email],
            'notes' => ['order_no' => $order->order_no],
        ];
    }

    /** Signature from the app after checkout (order_id|payment_id). */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) config('services.razorpay.secret'));

        return hash_equals($expected, $signature);
    }

    public function verifyWebhook(string $body, ?string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');

        return $secret !== '' && $signature && hash_equals(hash_hmac('sha256', $body, $secret), $signature);
    }

    public function status(Order $order): array
    {
        if ($order->payment_link_id) {
            $link = $this->api()->paymentLink->fetch($order->payment_link_id);
            $pay = collect($link['payments'] ?? [])->firstWhere('status', 'captured');

            return ['paid' => $link['status'] === 'paid', 'payment_id' => $pay['payment_id'] ?? null, 'method' => $pay['method'] ?? null, 'raw' => $link->toArray()];
        }
        if (! $order->gateway_order_id) {
            return ['paid' => false, 'payment_id' => null, 'method' => null, 'raw' => []];
        }
        $payments = $this->api()->order->fetch($order->gateway_order_id)->payments();
        foreach ($payments['items'] ?? [] as $p) {
            if ($p['status'] === 'captured') {
                return ['paid' => true, 'payment_id' => $p['id'], 'method' => $p['method'], 'raw' => $p->toArray()];
            }
        }

        return ['paid' => false, 'payment_id' => null, 'method' => null, 'raw' => []];
    }

    /** Razorpay Payment Link (shared on WhatsApp). */
    public function paymentLink(Order $order, int $expireMinutes): array
    {
        $link = $this->api()->paymentLink->create([
            'amount' => $order->total,
            'currency' => 'INR',
            'reference_id' => $order->order_no,
            'description' => mb_strimwidth($order->batch->course->title.' – '.$order->batch->name, 0, 250),
            'customer' => array_filter(['name' => $order->name, 'contact' => '+91'.$order->phone]),
            'notify' => ['sms' => false, 'email' => false],   // we share on WhatsApp ourselves
            'reminder_enable' => true,
            'expire_by' => now()->addMinutes(max(16, $expireMinutes))->timestamp,
            'callback_url' => rtrim(config('app.deep_link_host'), '/').'/paid/'.$order->order_no,
            'callback_method' => 'get',
            'notes' => ['order_no' => $order->order_no],
        ]);

        return ['id' => $link['id'], 'url' => $link['short_url']];
    }

    public function refund(string $paymentId, int $amount, array $notes = []): array
    {
        $refund = $this->api()->payment->fetch($paymentId)->refund(['amount' => $amount, 'notes' => $notes]);

        return ['id' => $refund['id'], 'status' => $refund['status']];
    }
}
