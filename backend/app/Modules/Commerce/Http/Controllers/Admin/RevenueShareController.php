<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Core\Support\Money;
use App\Models\Payout;
use App\Models\RevenueShareLedger;
use App\Modules\Commerce\Services\RevenueShareService;
use Illuminate\Http\Request;

class RevenueShareController extends Controller
{
    public function __construct(private RevenueShareService $service) {}

    public function summary()
    {
        return ApiResponse::ok($this->service->summary());
    }

    public function configure(Request $r)
    {
        abort_unless($r->user()->can('revenue_share.manage'), 403);
        $d = $r->validate(['enabled' => 'required|boolean', 'percent' => 'required|numeric|between:0,100', 'partner_name' => 'nullable|string|max:80']);

        return ApiResponse::ok($this->service->configure($d['enabled'], (float) $d['percent'], $d['partner_name'] ?? null), $d['enabled'] ? 'Revenue share on (applies to new payments)' : 'Revenue share off');
    }

    public function ledger(Request $r)
    {
        $q = RevenueShareLedger::with('payment.order:id,order_no,name,batch_id', 'payment.order.batch:id,name')
            ->when($r->query('month'), fn ($w, $v) => $w->where('created_at', '>=', $v.'-01')->where('created_at', '<', \Illuminate\Support\Carbon::parse($v.'-01')->addMonth()))
            ->latest('id')->paginate(50);

        return ApiResponse::ok(collect($q->items())->map(fn ($l) => [
            'date' => $l->created_at->toDateString(), 'order_no' => $l->payment?->order?->order_no, 'student' => $l->payment?->order?->name,
            'batch' => $l->payment?->order?->batch?->name, 'order_total' => Money::format($l->order_total), 'percent' => $l->percent, 'share' => Money::format($l->share_amount),
        ]), 'OK', 200, ['pagination' => ['page' => $q->currentPage(), 'total' => $q->total(), 'last_page' => $q->lastPage()]]);
    }

    public function payouts()
    {
        return ApiResponse::ok(Payout::with('creator:id,name')->latest('paid_on')->get()->map(fn ($p) => [
            'id' => $p->id, 'amount' => Money::format($p->amount), 'paid_on' => $p->paid_on->toDateString(), 'method' => $p->method,
            'reference' => $p->reference, 'note' => $p->note, 'by' => $p->creator?->name,
        ]));
    }

    public function storePayout(Request $r)
    {
        abort_unless($r->user()->can('revenue_share.manage'), 403);
        $d = $r->validate(['amount' => 'required|numeric|min:1', 'paid_on' => 'required|date|before_or_equal:today', 'method' => 'required|in:bank,upi,cash,cheque',
            'reference' => 'nullable|string|max:120', 'note' => 'nullable|string|max:300', 'allow_advance' => 'nullable|boolean']);
        $this->service->addPayout($d);

        return ApiResponse::created($this->service->summary(), 'Payout recorded');
    }
}
