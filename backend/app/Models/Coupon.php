<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'show_in_app' => 'boolean'];
    }

    /** Batches the coupon works on (empty = all batches that allow coupons). */
    public function batches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'coupon_batches');
    }

    /** Buyer must already own one of these (old-student offers). */
    public function requiredBatches(): BelongsToMany
    {
        return $this->belongsToMany(Batch::class, 'coupon_eligibility', 'coupon_id', 'required_batch_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coupon_users');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function discountFor(int $amount): int
    {
        $d = $this->type === 'percent' ? (int) floor($amount * $this->value / 100) : (int) $this->value;
        if ($this->max_discount) {
            $d = min($d, $this->max_discount);
        }

        return max(0, min($d, $amount));
    }
}
