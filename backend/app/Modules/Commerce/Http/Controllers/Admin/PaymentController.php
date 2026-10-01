<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Core\Support\Money;
use App\Core\Support\Phone;
use App\Models\Order;
use App\Modules\Commerce\Resources\OrderResource;
use App\Modules\Commerce\Services\InvoiceService;
use App\Modules\Commerce\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Transactions, payment links, manual admission, refunds, invoices. */
class PaymentController extends Controller
{
    public function __construct(private OrderService $orders, private InvoiceService $invoices, private \App\Modules\WhatsApp\Services\CommerceMessages $messages) {}

    public function index(Request $r)
    {
        $page = $this->filtered($r)->with('batch.course:id,title', 'coupon:id,code', 'invoice', 'creator:id,name')->latest('id')->paginate(min(100, $r->integer('per_page', 25)));
        $base = $this->filtered($r);

        return ApiResponse::ok(OrderResource::collection($page), 'OK', 200, ['summary' => [
            'collected' => Money::format((int) (clone $base)->where('status', 'paid')->sum('total')),
            'orders_paid' => (clone $base)->where('status', 'paid')->count(),
            'pending_links' => (clone $base)->where('channel', 'payment_link')->where('status', 'pending')->count(),
            'refunded' => Money::format((int) DB::table('refunds')->join('payments', 'payments.id', '=', 'refunds.payment_id')
                ->whereIn('payments.order_id', (clone $base)->select('id'))->where('refunds.status', 'processed')->sum('refunds.amount')),
            'by_gateway' => (clone $base)->where('status', 'paid')->groupBy('gateway')->selectRaw('gateway, SUM(total) t')->pluck('t', 'gateway')->map(fn ($v) => Money::format((int) $v)),
        ]]);
    }

    public function show(Order $order)
    {
        $order->load('batch.course', 'coupon', 'invoice', 'creator:id,name', 'user:id,name,phone,email', 'payments');

        return ApiResponse::ok((new OrderResource($order))->resolve() + [
            'payments' => $order->payments->map(fn ($p) => ['id' => $p->id, 'gateway_payment_id' => $p->gateway_payment_id, 'amount' => Money::format($p->amount), 'status' => $p->status, 'method' => $p->method, 'paid_at' => $p->paid_at?->toIso8601String()]),
            'refunds' => DB::table('refunds')->whereIn('payment_id', $order->payments->pluck('id'))->get(),
        ]);
    }

    public function export(Request $r)
    {
        $rows = $this->filtered($r)->with('batch.course:id,title', 'coupon:id,code', 'invoice:id,order_id,invoice_no')->orderBy('id');
        \App\Core\Support\Audit::log('payments.export', null, $r->all());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Order', 'Date', 'Name', 'Phone', 'Course', 'Batch', 'Price', 'Discount', 'Coupon', 'Paid', 'Gateway', 'Channel', 'Status', 'Invoice']);
            $rows->chunkById(500, function ($chunk) use ($out) {
                foreach ($chunk as $o) {
                    fputcsv($out, [$o->order_no, $o->created_at->format('Y-m-d H:i'), $o->name, $o->phone, $o->batch?->course?->title, $o->batch?->name,
                        Money::toRupees($o->amount), Money::toRupees($o->discount), $o->coupon?->code, Money::toRupees($o->total), $o->gateway, $o->channel, $o->status, $o->invoice?->invoice_no]);
                }
            });
            fclose($out);
        }, 'transactions_'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function createLink(Request $r)
    {
        $r->merge(['phone' => Phone::normalize($r->input('phone')) ?? $r->input('phone')]);
        $d = $r->validate([
            'phone' => 'required|regex:/^[6-9]\d{9}$/', 'name' => 'required|string|max:80', 'batch_id' => 'required|integer|exists:batches,id',
            'coupon' => 'nullable|string|max:30', 'amount' => 'nullable|numeric|min:1', 'expires_in_hours' => 'nullable|integer|between:1,720',
            'note' => 'nullable|string|max:300', 'send_whatsapp' => 'nullable|boolean',
        ]);
        $order = $this->orders->paymentLink($d, $r->user());
        $wa = ($d['send_whatsapp'] ?? false) ? $this->trySend(fn () => $this->messages->paymentLink($order)) : null;

        return ApiResponse::created((new OrderResource($order))->resolve() + ['whatsapp_url' => $this->orders->whatsappForLink($order), 'whatsapp' => $wa],
            $wa ? ($wa['status'] === 'sent' ? 'Payment link sent on WhatsApp ✅' : 'Link created, but WhatsApp failed: '.$wa['error']) : 'Payment link ready – share it on WhatsApp');
    }

    public function linkWhatsapp(Order $order)
    {
        abort_unless($order->payment_link_url, 404);

        return ApiResponse::ok(['whatsapp_url' => $this->orders->whatsappForLink($order->load('batch.course')), 'url' => $order->payment_link_url]);
    }

    /** "Check now" button for a pending link/order. */
    public function refresh(Order $order)
    {
        return ApiResponse::ok(new OrderResource($this->orders->refreshStatus($order)->load('batch.course', 'invoice')));
    }

    public function manualAdmission(Request $r)
    {
        $r->merge(['phone' => Phone::normalize($r->input('phone')) ?? $r->input('phone')]);
        $d = $r->validate([
            'phone' => 'required|regex:/^[6-9]\d{9}$/', 'name' => 'required|string|max:80', 'batch_id' => 'required|integer|exists:batches,id',
            'mode' => 'required|in:cash,bank,upi_outside,cheque,scholarship,free', 'amount' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:120', 'note' => 'nullable|string|max:300', 'expires_at' => 'nullable|date|after:today',
            'send_whatsapp' => 'nullable|boolean',
        ]);
        $order = $this->orders->manualAdmission($d, $r->user());
        $wa = null;
        if ($d['send_whatsapp'] ?? false) {
            $wa = $order->total > 0 ? $this->trySend(fn () => $this->messages->invoice($order)) : $this->trySend(fn () => $this->messages->admission($order));
        }

        return ApiResponse::created((new OrderResource($order->load('batch.course', 'invoice')))->resolve()
            + ['invoice_whatsapp_url' => $order->invoice ? $this->invoices->whatsappUrl($order->invoice) : null, 'whatsapp' => $wa],
            $wa ? ($wa['status'] === 'sent' ? 'Student admitted – WhatsApp sent ✅' : 'Student admitted, but WhatsApp failed: '.$wa['error']) : 'Student admitted');
    }

    /** Send the payment link through the WhatsApp API (not wa.me). */
    public function sendLinkWhatsapp(Order $order)
    {
        $m = $this->messages->paymentLink($order);

        return $m->status === 'sent' ? ApiResponse::ok(['status' => 'sent'], 'Payment link sent on WhatsApp') : ApiResponse::fail('WhatsApp failed: '.$m->error, 422);
    }

    /** Send the invoice PDF as a WhatsApp document through the API. */
    public function sendInvoiceWhatsapp(Order $order)
    {
        $m = $this->messages->invoice($order);

        return $m->status === 'sent' ? ApiResponse::ok(['status' => 'sent'], 'Invoice sent on WhatsApp') : ApiResponse::fail('WhatsApp failed: '.$m->error, 422);
    }

    private function trySend(callable $fn): array
    {
        try {
            $m = $fn();

            return ['status' => $m->status, 'error' => $m->error, 'id' => $m->id];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage(), 'id' => null];
        }
    }

    public function refund(Request $r, Order $order)
    {
        abort_unless($order->status === 'paid', 422, 'Only paid orders can be refunded.');
        $d = $r->validate(['amount' => 'required|numeric|min:1', 'reason' => 'required|string|max:200', 'revoke_access' => 'nullable|boolean']);
        $refund = $this->orders->refund($order, Money::toPaise($d['amount']), $d['reason'], (bool) ($d['revoke_access'] ?? true));

        return ApiResponse::ok($refund, 'Refund '.($refund->status === 'processed' ? 'processed' : 'started'));
    }

    public function invoice(Order $order)
    {
        abort_unless($order->status === 'paid' && $order->total > 0, 422, 'No invoice for this order.');
        $inv = $this->invoices->forOrder($order);

        return ApiResponse::ok(['invoice_no' => $inv->invoice_no, 'url' => $this->invoices->publicUrl($inv), 'whatsapp_url' => $this->invoices->whatsappUrl($inv)]);
    }

    private function filtered(Request $r): Builder
    {
        return Order::query()
            ->when($r->query('status'), fn ($w, $v) => $w->whereIn('status', (array) $v))
            ->when($r->query('gateway'), fn ($w, $v) => $w->where('gateway', $v))
            ->when($r->query('channel'), fn ($w, $v) => $w->where('channel', $v))
            ->when($r->query('batch_id'), fn ($w, $v) => $w->where('batch_id', $v))
            ->when($r->query('course_id'), fn ($w, $v) => $w->whereHas('batch', fn ($b) => $b->where('course_id', $v)))
            ->when($r->query('coupon_id'), fn ($w, $v) => $w->where('coupon_id', $v))
            ->when($r->query('from'), fn ($w, $v) => $w->where('created_at', '>=', $v))
            ->when($r->query('to'), fn ($w, $v) => $w->where('created_at', '<=', \Illuminate\Support\Carbon::parse($v)->endOfDay()))
            ->when($r->query('search'), fn ($w, $v) => $w->where(fn ($x) => $x->where('order_no', 'like', "%$v%")->orWhere('phone', 'like', "%$v%")->orWhere('name', 'like', "%$v%")));
    }
}
