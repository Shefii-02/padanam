<?php

namespace App\Modules\Commerce\Http\Controllers\App;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Batch;
use App\Models\Order;
use App\Modules\Commerce\Resources\OrderResource;
use App\Modules\Commerce\Services\OrderService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function quote(Request $r)
    {
        $d = $r->validate(['batch_id' => 'required|integer|exists:batches,id', 'coupon' => 'nullable|string|max:30']);

        return ApiResponse::ok($this->orders->quote(Batch::with('course')->findOrFail($d['batch_id']), $r->user(), $d['coupon'] ?? null));
    }

    public function checkout(Request $r)
    {
        $d = $r->validate(['batch_id' => 'required|integer|exists:batches,id', 'coupon' => 'nullable|string|max:30', 'gateway' => 'required|in:razorpay,phonepe']);

        return ApiResponse::ok($this->orders->checkout($r->user(), Batch::with('course')->findOrFail($d['batch_id']), $d['coupon'] ?? null, $d['gateway']));
    }

    public function verify(Request $r, Order $order)
    {
        abort_unless($order->user_id === $r->user()->id, 404);
        $d = $r->validate(['razorpay_order_id' => 'required|string', 'razorpay_payment_id' => 'required|string', 'razorpay_signature' => 'required|string']);
        $order = $this->orders->verifyRazorpay($order, $d['razorpay_order_id'], $d['razorpay_payment_id'], $d['razorpay_signature']);

        return ApiResponse::ok($this->paidPayload($order), 'Payment successful 🎉');
    }

    /** Poll after PhonePe redirect / if the app was closed during payment. */
    public function status(Request $r, Order $order)
    {
        abort_unless($order->user_id === $r->user()->id, 404);
        $order = $this->orders->refreshStatus($order);

        return ApiResponse::ok($this->paidPayload($order));
    }

    public function myOrders(Request $r)
    {
        return ApiResponse::ok(OrderResource::collection(Order::where('user_id', $r->user()->id)->whereIn('status', ['paid', 'refunded'])
            ->with('batch.course:id,title', 'invoice')->latest()->paginate(20)));
    }

    private function paidPayload(Order $order): array
    {
        $order->loadMissing('batch.course', 'invoice');

        return [
            'order_no' => $order->order_no,
            'status' => $order->status,
            'course_id' => $order->batch->course_id,
            'course' => $order->batch->course->title,
            'batch' => $order->batch->name,
            'total' => \App\Core\Support\Money::format($order->total),
            'invoice_url' => $order->invoice ? url('/api/v1/public/invoices/'.$order->invoice->public_token) : null,
        ];
    }
}
