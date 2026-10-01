<?php

namespace App\Modules\Commerce\Console;

use App\Models\Order;
use App\Modules\Commerce\Services\OrderService;
use Illuminate\Console\Command;

/** Before marking a link expired, ask Razorpay once – a payment may have come in while a webhook was missed. */
class ExpirePaymentLinks extends Command
{
    protected $signature = 'orders:expire-links';

    protected $description = 'Expire unpaid payment links and stale checkout orders';

    public function handle(OrderService $orders): int
    {
        $n = 0;
        Order::whereIn('status', ['created', 'pending'])
            ->where(fn ($q) => $q->where('link_expires_at', '<', now())->orWhere(fn ($x) => $x->whereNull('link_expires_at')->where('created_at', '<', now()->subHours(6))))
            ->chunkById(100, function ($list) use ($orders, &$n) {
                foreach ($list as $o) {
                    try {
                        $o = $orders->refreshStatus($o);
                    } catch (\Throwable) {
                    }
                    if ($o->status !== 'paid') {
                        $o->update(['status' => 'expired']);
                        $n++;
                    }
                }
            });
        $this->info("$n orders expired");

        return self::SUCCESS;
    }
}
