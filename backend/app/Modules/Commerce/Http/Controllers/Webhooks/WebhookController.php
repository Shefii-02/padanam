<?php

namespace App\Modules\Commerce\Http\Controllers\Webhooks;

use App\Core\Http\Controller;
use App\Models\Order;
use App\Models\WebhookLog;
use App\Modules\Commerce\Gateways\PhonePeGateway;
use App\Modules\Commerce\Gateways\RazorpayGateway;
use App\Modules\Commerce\Services\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gateways retry until they get 200, so: verify → log once (event id unique) → process → always 200 for known events.
 * Razorpay events to enable: payment.captured, order.paid, payment_link.paid
 */
class WebhookController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function razorpay(Request $r, RazorpayGateway $rz)
    {
        $body = $r->getContent();
        $ok = $rz->verifyWebhook($body, $r->header('X-Razorpay-Signature'));
        $payload = json_decode($body, true) ?? [];
        $log = $this->log('razorpay', $payload['event'] ?? null, $r->header('X-Razorpay-Event-Id') ?? ($payload['payload']['payment']['entity']['id'] ?? null).':'.($payload['event'] ?? ''), $payload, $ok);
        if (! $ok) {
            return response()->json(['ok' => false], 400);
        }
        if (! $log) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            $event = $payload['event'] ?? '';
            $pay = $payload['payload']['payment']['entity'] ?? null;
            $order = null;
            if ($event === 'payment_link.paid') {
                $linkId = $payload['payload']['payment_link']['entity']['id'] ?? null;
                $order = Order::firstWhere('payment_link_id', $linkId);
            } elseif (in_array($event, ['payment.captured', 'order.paid'], true) && $pay) {
                $order = Order::firstWhere('gateway_order_id', $pay['order_id'] ?? '')
                    ?? Order::firstWhere('order_no', $pay['notes']['order_no'] ?? '');
            }
            if ($order && $pay && ($pay['status'] ?? '') === 'captured') {
                $this->orders->markPaid($order, 'razorpay', $pay['id'], $pay['method'] ?? null, $pay, (int) $pay['amount']);
            }
            $log->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['error' => $e->getMessage()]);
            Log::channel('payments')->error('Razorpay webhook: '.$e->getMessage());

            return response()->json(['ok' => false], 500);   // Razorpay retries
        }

        return response()->json(['ok' => true]);
    }

    public function phonepe(Request $r, PhonePeGateway $pp)
    {
        $data = $pp->verifyCallback((string) $r->input('response'), $r->header('X-VERIFY'));
        $log = $this->log('phonepe', $data['code'] ?? null, ($data['data']['transactionId'] ?? uniqid()).':'.($data['code'] ?? ''), $data ?? $r->all(), (bool) $data);
        if (! $data) {
            return response()->json(['ok' => false], 400);
        }
        if (! $log) {
            return response()->json(['ok' => true]);
        }
        $order = Order::firstWhere('order_no', $data['data']['merchantTransactionId'] ?? '');
        if ($order) {
            // never trust the callback alone – confirm with the status API
            $this->orders->refreshStatus($order);
        }
        $log->update(['processed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function log(string $gateway, ?string $event, ?string $eventId, array $payload, bool $ok): ?WebhookLog
    {
        try {
            return WebhookLog::create(['gateway' => $gateway, 'event' => $event, 'event_id' => $ok ? $eventId : null, 'payload' => $payload, 'signature_ok' => $ok]);
        } catch (UniqueConstraintViolationException) {
            return null;   // already received
        }
    }
}
