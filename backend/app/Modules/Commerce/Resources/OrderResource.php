<?php

namespace App\Modules\Commerce\Resources;

use App\Core\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_no' => $this->order_no,
            'user_id' => $this->user_id,
            'name' => $this->name ?? $this->user?->name,
            'phone' => $this->phone,
            'course' => $this->whenLoaded('batch', fn () => $this->batch?->course?->title),
            'batch' => $this->whenLoaded('batch', fn () => $this->batch?->name),
            'batch_id' => $this->batch_id,
            'amount' => Money::format($this->amount),
            'discount' => Money::format($this->discount),
            'total' => Money::format($this->total),
            'total_paise' => $this->total,
            'coupon' => $this->whenLoaded('coupon', fn () => $this->coupon?->code),
            'gateway' => $this->gateway,
            'channel' => $this->channel,
            'status' => $this->status,
            'manual_mode' => $this->manual_mode,
            'manual_reference' => $this->manual_reference,
            'payment_link_url' => $this->payment_link_url,
            'link_expires_at' => $this->link_expires_at?->toIso8601String(),
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? [
                'no' => $this->invoice->invoice_no,
                'url' => url('/api/v1/public/invoices/'.$this->invoice->public_token),
            ] : null),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
