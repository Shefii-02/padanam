<?php

namespace App\Modules\Commerce\Services;

use App\Core\Support\Audit;
use App\Core\Support\DomainException;
use App\Core\Support\Money;
use App\Core\Support\Settings;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\RevenueShareLedger;

/**
 * Off by default. When on, every NEW captured payment adds (total × %) to the partner's share.
 * Balance = total share − payouts.   e.g. ₹15,000 share − ₹10,000 paid = ₹5,000 balance.
 * Refunds reduce the share automatically.
 */
class RevenueShareService
{
    public function record(Payment $payment): void
    {
        if (! Settings::get('revenue_share.enabled') || $payment->status !== 'captured' || $payment->amount <= 0) {
            return;
        }
        $pct = (float) Settings::get('revenue_share.percent', 0);
        if ($pct <= 0) {
            return;
        }
        RevenueShareLedger::firstOrCreate(['payment_id' => $payment->id], [
            'order_total' => $payment->amount, 'percent' => $pct, 'share_amount' => (int) round($payment->amount * $pct / 100),
        ]);
    }

    public function onRefund(Payment $payment, int $refunded): void
    {
        $row = RevenueShareLedger::firstWhere('payment_id', $payment->id);
        if ($row) {
            $row->update(['share_amount' => max(0, $row->share_amount - (int) round($refunded * $row->percent / 100))]);
        }
    }

    public function summary(): array
    {
        $share = (int) RevenueShareLedger::sum('share_amount');
        $paid = (int) Payout::sum('amount');

        return [
            'enabled' => (bool) Settings::get('revenue_share.enabled'),
            'percent' => (float) Settings::get('revenue_share.percent', 0),
            'partner_name' => Settings::get('revenue_share.partner_name'),
            'total_share' => Money::toRupees($share), 'total_share_text' => Money::format($share),
            'paid_out' => Money::toRupees($paid), 'paid_out_text' => Money::format($paid),
            'balance' => Money::toRupees($share - $paid), 'balance_text' => Money::format($share - $paid),
            'this_month_share' => Money::format((int) RevenueShareLedger::where('created_at', '>=', now()->startOfMonth())->sum('share_amount')),
        ];
    }

    public function configure(bool $enabled, float $percent, ?string $partner): array
    {
        if ($percent < 0 || $percent > 100) {
            throw new DomainException('Percent must be between 0 and 100.');
        }
        Settings::set('revenue_share.enabled', $enabled);
        Settings::set('revenue_share.percent', $percent);
        if ($partner) {
            Settings::set('revenue_share.partner_name', $partner);
        }
        Audit::log('revenue_share.configure', null, compact('enabled', 'percent'));

        return $this->summary();
    }

    public function addPayout(array $d): Payout
    {
        $amount = Money::toPaise($d['amount']);
        $balance = (int) RevenueShareLedger::sum('share_amount') - (int) Payout::sum('amount');
        if ($amount > $balance && empty($d['allow_advance'])) {
            throw new DomainException('Payout is more than the balance ('.Money::format($balance).'). Tick "advance" to allow it.');
        }
        $p = Payout::create(['amount' => $amount, 'paid_on' => $d['paid_on'], 'method' => $d['method'], 'reference' => $d['reference'] ?? null,
            'note' => $d['note'] ?? null, 'created_by' => auth()->id()]);
        Audit::log('revenue_share.payout', $p, ['amount' => $amount]);

        return $p;
    }
}
