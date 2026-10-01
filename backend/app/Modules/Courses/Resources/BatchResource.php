<?php

namespace App\Modules\Courses\Resources;

use App\Core\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Batch */
class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'name' => $this->name,
            'code' => $this->code,
            'is_default' => $this->is_default,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'price' => Money::toRupees($this->price),
            'mrp' => Money::toRupees($this->mrp),
            'price_text' => $this->is_free ? 'Free' : Money::format($this->price),
            'mrp_text' => $this->mrp > $this->price ? Money::format($this->mrp) : null,
            'discount_percent' => $this->discountPercent(),
            'is_free' => $this->is_free,
            'validity_type' => $this->validity_type,
            'validity_days' => $this->validity_days,
            'valid_until' => $this->valid_until?->toDateString(),
            'validity_text' => match ($this->validity_type) {
                'fixed_date' => 'Until '.$this->valid_until?->format('j M Y'),
                'days' => ($this->validity_days ?: 365).' days',
                default => 'Lifetime access',
            },
            'seat_limit' => $this->seat_limit,
            'seats_taken' => $this->seats_taken,
            'seats_left' => $this->seatsLeft(),
            'enrollment_open' => $this->enrollment_open,
            'is_purchasable' => $this->isPurchasable(),
            'coupon_policy' => $this->coupon_policy,
            'status' => $this->status,
            'staff' => $this->whenLoaded('staff', fn () => $this->staff->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->pivot->role])),
            'coupon_ids' => $this->whenLoaded('coupons', fn () => $this->coupons->pluck('id')),
            'students_count' => $this->whenCounted('enrollments'),
        ];
    }
}
