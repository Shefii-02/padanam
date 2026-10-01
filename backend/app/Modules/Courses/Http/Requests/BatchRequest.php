<?php

namespace App\Modules\Courses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchRequest extends FormRequest
{
    public function rules(): array
    {
        $c = $this->isMethod('post');

        return [
            'name' => [$c ? 'required' : 'sometimes', 'string', 'max:80'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'price' => [$c ? 'required' : 'sometimes', 'numeric', 'min:0', 'max:500000'],
            'mrp' => ['nullable', 'numeric', 'min:0', 'max:500000'],
            'is_free' => ['nullable', 'boolean'],
            'validity_type' => [$c ? 'required' : 'sometimes', 'in:fixed_date,days,lifetime'],
            'validity_days' => ['nullable', 'required_if:validity_type,days', 'integer', 'min:1', 'max:3650'],
            'valid_until' => ['nullable', 'required_if:validity_type,fixed_date', 'date', 'after:today'],
            'seat_limit' => ['nullable', 'integer', 'min:1'],
            'enrollment_open' => ['nullable', 'boolean'],
            'coupon_policy' => ['nullable', 'in:allow,deny,custom'],
            'coupon_ids' => ['nullable', 'array'],
            'coupon_ids.*' => ['integer', 'exists:coupons,id'],
            'staff' => ['nullable', 'array'],
            'staff.*.user_id' => ['required_with:staff', 'integer', 'exists:users,id'],
            'staff.*.role' => ['nullable', 'in:manager,teacher'],
            'sort' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,closed,archived'],
        ];
    }
}
