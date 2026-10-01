<?php

namespace App\Modules\Courses\DTOs;

use App\Core\DTO\Data;
use App\Core\Support\Money;
use Illuminate\Http\Request;

final class BatchData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $starts_at = null,
        public readonly ?string $ends_at = null,
        public readonly ?int $price = null,        // paise
        public readonly ?int $mrp = null,          // paise
        public readonly ?bool $is_free = null,
        public readonly ?string $validity_type = null,
        public readonly ?int $validity_days = null,
        public readonly ?string $valid_until = null,
        public readonly ?int $seat_limit = null,
        public readonly ?bool $enrollment_open = null,
        public readonly ?string $coupon_policy = null,
        public readonly ?array $coupon_ids = null,
        public readonly ?array $staff = null,
        public readonly ?int $sort = null,
        public readonly ?string $status = null,
    ) {}

    public static function fromRequest(Request $r): static
    {
        $keys = ['name', 'starts_at', 'ends_at', 'price', 'mrp', 'is_free', 'validity_type', 'validity_days', 'valid_until',
            'seat_limit', 'enrollment_open', 'coupon_policy', 'coupon_ids', 'staff', 'sort', 'status'];

        return (new self(
            name: $r->input('name'),
            starts_at: $r->input('starts_at'),
            ends_at: $r->input('ends_at'),
            price: $r->has('price') ? Money::toPaise($r->input('price')) : null,     // API takes rupees
            mrp: $r->has('mrp') ? Money::toPaise($r->input('mrp')) : null,
            is_free: $r->has('is_free') ? $r->boolean('is_free') : null,
            validity_type: $r->input('validity_type'),
            validity_days: $r->input('validity_days'),
            valid_until: $r->input('valid_until'),
            seat_limit: $r->input('seat_limit'),
            enrollment_open: $r->has('enrollment_open') ? $r->boolean('enrollment_open') : null,
            coupon_policy: $r->input('coupon_policy'),
            coupon_ids: $r->input('coupon_ids'),
            staff: $r->input('staff'),
            sort: $r->input('sort'),
            status: $r->input('status'),
        ))->withProvided(array_keys($r->only($keys)));
    }

    public function batchColumns(): array
    {
        $d = array_diff_key($this->toArray(), array_flip(['coupon_ids', 'staff']));
        if (array_key_exists('price', $d) && ! array_key_exists('is_free', $d)) {
            $d['is_free'] = ((int) $d['price']) === 0;
        }

        return $d;
    }
}
