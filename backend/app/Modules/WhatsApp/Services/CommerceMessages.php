<?php

namespace App\Modules\WhatsApp\Services;

use App\Core\Support\DomainException;
use App\Core\Support\Money;
use App\Models\Order;
use App\Models\WhatsAppMessage;
use App\Modules\Commerce\Services\InvoiceService;

/** Payment link, invoice (PDF as a document) and admission messages sent through the WhatsApp API. */
class CommerceMessages
{
    public function __construct(private WhatsAppService $wa, private InvoiceService $invoices) {}

    public function vars(Order $order): array
    {
        $order->loadMissing('batch.course');

        return [
            'name' => $order->name ?: 'Student', 'phone' => $order->phone, 'course' => $order->batch?->course?->title,
            'batch' => $order->batch?->name, 'amount' => Money::format($order->total), 'link' => $order->payment_link_url, 'order_no' => $order->order_no,
        ];
    }

    public function paymentLink(Order $order, string $template = 'payment_link', array $extra = []): WhatsAppMessage
    {
        if (! $order->payment_link_url) {
            throw new DomainException('This order has no payment link.');
        }

        return $this->wa->sendText($order->phone, $this->wa->render($template, $this->vars($order) + $extra),
            $template === 'payment_link' ? 'payment_link' : 'swap', ['order_id' => $order->id, 'user_id' => $order->user_id]);
    }

    /** Sends the invoice PDF itself (document message) with the invoice text as caption. */
    public function invoice(Order $order): WhatsAppMessage
    {
        if ($order->status !== 'paid' || $order->total <= 0) {
            throw new DomainException('Invoices are only for paid orders.');
        }
        $inv = $this->invoices->forOrder($order);
        $url = $this->invoices->publicUrl($inv);
        $caption = $this->wa->render('invoice', $this->vars($order) + ['invoice_no' => $inv->invoice_no, 'invoice_url' => $url]);

        return $this->wa->sendDocument($order->phone, $url, $inv->invoice_no.'.pdf', $caption, 'invoice', ['order_id' => $order->id, 'user_id' => $order->user_id]);
    }

    public function admission(Order $order): WhatsAppMessage
    {
        return $this->wa->sendText($order->phone, $this->wa->render('admission', $this->vars($order)), 'admission', ['order_id' => $order->id, 'user_id' => $order->user_id]);
    }

    /** Called after a payment is completed (webhook / app / manual). Uses the "auto" switches. */
    public function afterPaid(Order $order): void
    {
        if (! $this->wa->isReady('share')) {
            return;
        }
        $auto = \App\Core\Support\Settings::get('whatsapp.auto', []);
        try {
            if (($auto['invoice'] ?? true) && $order->total > 0) {
                $this->invoice($order);
            } elseif (($auto['admission'] ?? false)) {
                $this->admission($order);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Auto WhatsApp after payment failed', ['order' => $order->order_no, 'error' => $e->getMessage()]);
        }
    }
}
