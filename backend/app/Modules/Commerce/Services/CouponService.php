<?php

namespace App\Modules\Commerce\Services;

use App\Core\Support\DomainException;
use App\Core\Support\Money;
use App\Models\Batch;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;

/**
 * Coupon rules, checked in this order:
 *  batch policy (deny / custom list) → active + dates → where it applies → total & per-user limits
 *  → audience (new users / existing students / chosen people) → must own a batch (old-student offers) → minimum amount
 */
class CouponService
{
    public function apply(string $code, Batch $batch, ?User $user, int $amount): array
    {
        $coupon = Coupon::with('batches:id', 'requiredBatches:id', 'users:id')->where('code', strtoupper(trim($code)))->first();
        if (! $coupon) {
            throw new DomainException('This coupon code is not valid.');
        }
        $this->check($coupon, $batch, $user, $amount);
        $discount = $coupon->discountFor($amount);

        return ['coupon' => $coupon, 'discount' => $discount];
    }

    public function check(Coupon $c, Batch $batch, ?User $user, int $amount): void
    {
        if ($batch->coupon_policy === 'deny') {
            throw new DomainException('Coupons cannot be used for this batch.');
        }
        if (! $c->is_active || ($c->starts_at && $c->starts_at->isFuture())) {
            throw new DomainException('This coupon is not active.');
        }
        if ($c->ends_at && $c->ends_at->isPast()) {
            throw new DomainException('This coupon has expired.');
        }
        $applies = $c->batches->isEmpty() ? $batch->coupon_policy === 'allow' : $c->batches->contains('id', $batch->id);
        if (! $applies) {
            throw new DomainException('This coupon is not for this batch.');
        }
        if ($c->total_limit !== null && $c->used_count >= $c->total_limit) {
            throw new DomainException('This coupon has been fully used.');
        }
        if ($user && $c->per_user_limit && CouponRedemption::where('coupon_id', $c->id)->where('user_id', $user->id)->count() >= $c->per_user_limit) {
            throw new DomainException('You have already used this coupon.');
        }
        match ($c->audience) {
            'new_users' => $user && Order::where('user_id', $user->id)->where('status', 'paid')->where('total', '>', 0)->exists()
                ? throw new DomainException('This coupon is only for first-time buyers.') : null,
            'existing_students' => ! $user || ! Enrollment::where('user_id', $user->id)->exists()
                ? throw new DomainException('This coupon is only for existing Padanam students.') : null,
            'specific_users' => ! $user || ! $c->users->contains('id', $user->id)
                ? throw new DomainException('This coupon is not available for your account.') : null,
            default => null,
        };
        if ($c->requiredBatches->isNotEmpty()) {
            $owns = $user && Enrollment::where('user_id', $user->id)->whereIn('batch_id', $c->requiredBatches->pluck('id'))->exists();
            if (! $owns) {
                throw new DomainException('This offer is for students of our earlier batches.');
            }
        }
        if ($amount < $c->min_amount) {
            throw new DomainException('Minimum order for this coupon is '.Money::format($c->min_amount).'.');
        }
    }

    /** Offers to show on the checkout screen (only ones this student can actually use). */
    public function offersFor(Batch $batch, ?User $user): array
    {
        return Coupon::with('batches:id', 'requiredBatches:id', 'users:id')->where('show_in_app', true)->where('is_active', true)->get()
            ->filter(function ($c) use ($batch, $user) {
                try {
                    $this->check($c, $batch, $user, $batch->price);

                    return true;
                } catch (DomainException) {
                    return false;
                }
            })
            ->map(fn ($c) => [
                'code' => $c->code, 'title' => $c->title,
                'saves' => Money::format($c->discountFor($batch->price)),
                'ends_at' => $c->ends_at?->toIso8601String(),
            ])->values()->all();
    }

    public function save(array $d, ?Coupon $c = null): Coupon
    {
        $d['code'] = strtoupper(preg_replace('/\s+/', '', $d['code'] ?? $c?->code));
        foreach (['max_discount', 'min_amount'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] !== null) {
                $d[$k] = Money::toPaise($d[$k]);
            }
        }
        if (($d['type'] ?? $c?->type) === 'flat' && isset($d['value'])) {
            $d['value'] = Money::toPaise($d['value']);
        }
        if (($d['type'] ?? $c?->type) === 'percent' && isset($d['value']) && $d['value'] > 100) {
            throw new DomainException('Percent discount cannot be more than 100.');
        }
        $rel = array_intersect_key($d, array_flip(['batch_ids', 'required_batch_ids', 'user_ids']));
        $d = array_diff_key($d, $rel);
        $c = $c ? tap($c)->update($d) : Coupon::create($d + ['created_by' => auth()->id()]);
        if (array_key_exists('batch_ids', $rel)) {
            $c->batches()->sync($rel['batch_ids'] ?? []);
        }
        if (array_key_exists('required_batch_ids', $rel)) {
            $c->requiredBatches()->sync($rel['required_batch_ids'] ?? []);
        }
        if (array_key_exists('user_ids', $rel)) {
            $c->users()->sync($rel['user_ids'] ?? []);
        }

        return $c->load('batches:id,name', 'requiredBatches:id,name');
    }
}
