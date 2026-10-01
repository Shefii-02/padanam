<?php

namespace App\Modules\Commerce\Gateways;

use App\Core\Support\DomainException;
use App\Models\Order;
use Illuminate\Support\Facades\Http;

/**
 * PhonePe PG – Standard Checkout (X-VERIFY checksum API).
 * Newer PhonePe merchant accounts may be issued the OAuth-based v2 API instead; keep this class as the only
 * place that talks to PhonePe so switching versions touches nothing else.
 */
class PhonePeGateway implements PaymentGateway
{
    private function cfg(string $k): string
    {
        $v = (string) config('services.phonepe.'.$k);
        if ($v === '') {
            throw new DomainException('PhonePe is not configured yet.', 503);
        }

        return $v;
    }

    private function checksum(string $payloadOrPath): string
    {
        return hash('sha256', $payloadOrPath.$this->cfg('salt_key')).'###'.$this->cfg('salt_index');
    }

    public function checkout(Order $order): array
    {
        $payload = base64_encode(json_encode([
            'merchantId' => $this->cfg('merchant_id'),
            'merchantTransactionId' => $order->order_no,
            'merchantUserId' => 'U'.$order->user_id,
            'amount' => $order->total,
            'redirectUrl' => rtrim(config('app.deep_link_host'), '/').'/paid/'.$order->order_no,
            'redirectMode' => 'REDIRECT',
            'callbackUrl' => url('/api/v1/webhooks/phonepe'),
            'mobileNumber' => $order->phone,
            'paymentInstrument' => ['type' => 'PAY_PAGE'],
        ]));
        $res = Http::withHeaders(['X-VERIFY' => $this->checksum($payload.'/pg/v1/pay'), 'Content-Type' => 'application/json'])
            ->post($this->cfg('base_url').'/pg/v1/pay', ['request' => $payload])->json();

        if (! ($res['success'] ?? false)) {
            throw new DomainException('PhonePe: '.($res['message'] ?? 'could not start payment'), 502);
        }
        $order->update(['gateway_order_id' => $order->order_no, 'status' => 'pending']);

        return ['gateway' => 'phonepe', 'redirect_url' => $res['data']['instrumentResponse']['redirectInfo']['url'] ?? null, 'order_no' => $order->order_no];
    }

    public function status(Order $order): array
    {
        $path = '/pg/v1/status/'.$this->cfg('merchant_id').'/'.$order->order_no;
        $res = Http::withHeaders(['X-VERIFY' => $this->checksum($path), 'X-MERCHANT-ID' => $this->cfg('merchant_id')])
            ->get($this->cfg('base_url').$path)->json();
        $paid = ($res['code'] ?? '') === 'PAYMENT_SUCCESS' && (int) ($res['data']['amount'] ?? 0) === (int) $order->total;

        return [
            'paid' => $paid,
            'payment_id' => $res['data']['transactionId'] ?? null,
            'method' => $res['data']['paymentInstrument']['type'] ?? null,
            'raw' => $res ?? [],
        ];
    }

    /** Callback body: {"response": base64}, header X-VERIFY = sha256(response + salt) ### index */
    public function verifyCallback(string $base64, ?string $xVerify): ?array
    {
        if (! $xVerify || ! hash_equals($this->checksum($base64), $xVerify)) {
            return null;
        }

        return json_decode(base64_decode($base64), true);
    }
}
