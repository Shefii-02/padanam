<?php

namespace App\Modules\Commerce\Gateways;

use App\Models\Order;

interface PaymentGateway
{
    /** Creates the gateway order and returns what the app needs to open checkout. */
    public function checkout(Order $order): array;

    /** Asks the gateway for the real status: ['paid' => bool, 'payment_id' => ?, 'method' => ?, 'raw' => []] */
    public function status(Order $order): array;
}
