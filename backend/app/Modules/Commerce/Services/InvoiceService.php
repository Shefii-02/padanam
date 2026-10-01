<?php

namespace App\Modules\Commerce\Services;

use App\Core\Support\Code;
use App\Core\Support\Money;
use App\Core\Support\Settings;
use App\Models\Invoice;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * GST invoice for every paid order. Numbering restarts each financial year: INV-2627-00001.
 * Kerala buyer (state code 32) → CGST + SGST, otherwise IGST. Prices include GST by default.
 */
class InvoiceService
{
    public function forOrder(Order $order): Invoice
    {
        if ($order->invoice) {
            return $order->invoice;
        }
        $order->loadMissing('user', 'batch.course');

        $invoice = DB::transaction(function () use ($order) {
            $gst = (float) Settings::get('invoice.gst_percent', 18);
            $inclusive = (bool) Settings::get('invoice.prices_include_gst', true);
            $total = $order->total;
            $subtotal = $inclusive ? (int) round($total / (1 + $gst / 100)) : $total;
            $tax = $inclusive ? $total - $subtotal : (int) round($total * $gst / 100);
            $total = $subtotal + $tax;

            $fy = now()->month >= 4 ? now()->format('y').now()->addYear()->format('y') : now()->subYear()->format('y').now()->format('y');
            $prefix = Settings::get('invoice.prefix', 'INV-').$fy.'-';
            $last = Invoice::where('invoice_no', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('invoice_no');
            $next = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

            return Invoice::create([
                'invoice_no' => $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT),
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'seller' => Settings::get('invoice.seller'),
                'buyer' => ['name' => $order->name ?? $order->user?->name, 'phone' => $order->phone, 'email' => $order->user?->email,
                    'district' => $order->user?->district, 'state' => $order->user?->state ?? 'Kerala'],
                'public_token' => Code::token(32),
            ]);
        });

        $this->renderPdf($invoice);

        return $invoice;
    }

    public function renderPdf(Invoice $invoice): string
    {
        $invoice->loadMissing('order.batch.course', 'order.coupon');
        $html = $this->html($invoice);
        $path = 'invoices/'.$invoice->invoice_no.'.pdf';
        Storage::disk('local')->put($path, Pdf::loadHTML($html)->setPaper('a4')->output());
        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    public function publicUrl(Invoice $invoice): string
    {
        return url('/api/v1/public/invoices/'.$invoice->public_token);
    }

    public function whatsappUrl(Invoice $invoice): string
    {
        $text = "Hi {$invoice->buyer['name']}, thank you for joining Padanam! 🎉\nInvoice {$invoice->invoice_no} for ".Money::format($invoice->total).":\n".$this->publicUrl($invoice);

        return 'https://wa.me/91'.$invoice->buyer['phone'].'?text='.rawurlencode($text);
    }

    private function html(Invoice $i): string
    {
        $o = $i->order;
        $s = $i->seller;
        $b = $i->buyer;
        $intra = ($s['state_code'] ?? '32') === '32' && strtolower($b['state'] ?? 'kerala') === 'kerala';
        $gst = (float) Settings::get('invoice.gst_percent', 18);
        $taxRows = $intra
            ? '<tr><td>CGST '.($gst / 2).'%</td><td class="r">'.Money::format(intdiv($i->tax, 2)).'</td></tr><tr><td>SGST '.($gst / 2).'%</td><td class="r">'.Money::format($i->tax - intdiv($i->tax, 2)).'</td></tr>'
            : '<tr><td>IGST '.$gst.'%</td><td class="r">'.Money::format($i->tax).'</td></tr>';
        $discount = $o->discount ? '<tr><td>Discount'.($o->coupon ? ' ('.e($o->coupon->code).')' : '').'</td><td class="r">− '.Money::format($o->discount).'</td></tr>' : '';

        return '<html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;font-size:12px;color:#1f2937}
            .h{display:flex;justify-content:space-between} h1{font-size:20px;margin:0;color:#0F766E}
            table{width:100%;border-collapse:collapse;margin-top:14px} td,th{padding:8px;border-bottom:1px solid #e5e7eb;text-align:left}
            .r{text-align:right} .tot td{font-weight:bold;font-size:14px;border-top:2px solid #0F766E} .muted{color:#6b7280;font-size:11px}
            </style></head><body>
            <table style="margin:0"><tr><td style="border:0"><h1>'.e($s['name'] ?? 'Padanam').'</h1><div class="muted">'.e($s['address'] ?? '').'<br>'.($s['gstin'] ? 'GSTIN: '.e($s['gstin']) : '').'</div></td>
            <td class="r" style="border:0"><b>TAX INVOICE</b><br>'.e($i->invoice_no).'<br><span class="muted">'.$i->created_at->format('d M Y').'</span></td></tr></table>
            <p><b>Billed to</b><br>'.e($b['name'] ?? '').'<br>+91 '.e($b['phone'] ?? '').($b['email'] ? '<br>'.e($b['email']) : '').'<br>'.e(trim(($b['district'] ?? '').', '.($b['state'] ?? ''), ', ')).'</p>
            <table><tr><th>Description</th><th class="r">Amount</th></tr>
            <tr><td>'.e($o->batch->course->title).'<br><span class="muted">'.e($o->batch->name).' · Order '.e($o->order_no).' · SAC 999293</span></td><td class="r">'.Money::format($o->amount).'</td></tr>
            '.$discount.'<tr><td>Taxable value</td><td class="r">'.Money::format($i->subtotal).'</td></tr>'.$taxRows.'
            <tr class="tot"><td>Total paid</td><td class="r">'.Money::format($i->total).'</td></tr></table>
            <p class="muted">Paid via '.e(ucfirst($o->gateway)).($o->paid_at ? ' on '.$o->paid_at->format('d M Y, g:i A') : '').'. This is a computer-generated invoice.</p>
            </body></html>';
    }
}
