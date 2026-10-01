<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Core\Support\Money;
use App\Models\Coupon;
use App\Modules\Commerce\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function __construct(private CouponService $coupons) {}

    public function index(Request $r)
    {
        $q = Coupon::with('batches:id,name', 'requiredBatches:id,name')
            ->when($r->query('search'), fn ($w, $v) => $w->where('code', 'like', "%$v%")->orWhere('title', 'like', "%$v%"))
            ->when($r->query('state') === 'active', fn ($w) => $w->where('is_active', true)->where(fn ($x) => $x->whereNull('ends_at')->orWhere('ends_at', '>', now())))
            ->when($r->query('state') === 'expired', fn ($w) => $w->where('ends_at', '<', now()))
            ->withSum('redemptions', 'discount')->latest('id')->paginate(30);

        return ApiResponse::ok(collect($q->items())->map(fn ($c) => $this->row($c)), 'OK', 200, ['pagination' => ['page' => $q->currentPage(), 'total' => $q->total(), 'last_page' => $q->lastPage()]]);
    }

    public function store(Request $r)
    {
        return ApiResponse::created($this->row($this->coupons->save($this->rules($r, null))), 'Coupon created');
    }

    public function update(Request $r, Coupon $coupon)
    {
        return ApiResponse::ok($this->row($this->coupons->save($this->rules($r, $coupon), $coupon)), 'Coupon saved');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->redemptions()->exists() ? $coupon->update(['is_active' => false]) : $coupon->delete();

        return ApiResponse::ok(null, 'Coupon removed');
    }

    private function row(Coupon $c): array
    {
        return [
            'id' => $c->id, 'code' => $c->code, 'title' => $c->title, 'type' => $c->type,
            'value' => $c->type === 'flat' ? Money::toRupees($c->value) : $c->value,
            'value_text' => $c->type === 'flat' ? Money::format($c->value).' off' : $c->value.'% off',
            'max_discount' => $c->max_discount ? Money::toRupees($c->max_discount) : null, 'min_amount' => Money::toRupees($c->min_amount),
            'starts_at' => $c->starts_at?->toIso8601String(), 'ends_at' => $c->ends_at?->toIso8601String(),
            'total_limit' => $c->total_limit, 'per_user_limit' => $c->per_user_limit, 'used_count' => $c->used_count,
            'audience' => $c->audience, 'is_active' => $c->is_active, 'show_in_app' => $c->show_in_app,
            'batches' => $c->relationLoaded('batches') ? $c->batches->map->only(['id', 'name']) : [],
            'required_batches' => $c->relationLoaded('requiredBatches') ? $c->requiredBatches->map->only(['id', 'name']) : [],
            'total_discount_given' => Money::format((int) ($c->redemptions_sum_discount ?? 0)),
            'state' => ! $c->is_active ? 'paused' : ($c->ends_at?->isPast() ? 'expired' : ($c->total_limit && $c->used_count >= $c->total_limit ? 'used_up' : 'active')),
        ];
    }

    private function rules(Request $r, ?Coupon $c): array
    {
        $new = ! $c;

        return $r->validate([
            'code' => [$new ? 'required' : 'sometimes', 'alpha_num', 'max:30', Rule::unique('coupons', 'code')->ignore($c?->id)],
            'title' => [$new ? 'required' : 'sometimes', 'string', 'max:100'],
            'type' => [$new ? 'required' : 'sometimes', 'in:percent,flat'],
            'value' => [$new ? 'required' : 'sometimes', 'numeric', 'min:1'],
            'max_discount' => 'nullable|numeric|min:1', 'min_amount' => 'nullable|numeric|min:0',
            'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at',
            'total_limit' => 'nullable|integer|min:1', 'per_user_limit' => 'nullable|integer|min:1|max:100',
            'audience' => 'nullable|in:all,new_users,existing_students,specific_users',
            'is_active' => 'nullable|boolean', 'show_in_app' => 'nullable|boolean',
            'batch_ids' => 'nullable|array', 'batch_ids.*' => 'integer|exists:batches,id',
            'required_batch_ids' => 'nullable|array', 'required_batch_ids.*' => 'integer|exists:batches,id',
            'user_ids' => 'nullable|array', 'user_ids.*' => 'integer|exists:users,id',
        ]);
    }
}
