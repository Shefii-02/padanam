<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Batch extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'valid_until' => 'date',
            'is_free' => 'boolean',
            'is_default' => 'boolean',
            'enrollment_open' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'batch_staff')->withPivot('role')->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function liveClasses(): HasMany
    {
        return $this->hasMany(LiveClass::class);
    }

    public function chatRoom(): HasOne
    {
        return $this->hasOne(ChatRoom::class)->where('type', 'batch_group');
    }

    /** Coupons explicitly allowed for this batch (coupon_policy = custom). */
    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_batches');
    }

    public function seatsLeft(): ?int
    {
        return $this->seat_limit === null ? null : max(0, $this->seat_limit - $this->seats_taken);
    }

    public function isPurchasable(): bool
    {
        return $this->status === 'active'
            && $this->enrollment_open
            && ($this->seat_limit === null || $this->seats_taken < $this->seat_limit)
            && (! $this->ends_at || $this->ends_at->endOfDay()->isFuture());
    }

    /** Access end date for someone joining now. */
    public function expiryFor(?Carbon $from = null): ?Carbon
    {
        $from ??= now();

        return match ($this->validity_type) {
            'fixed_date' => $this->valid_until?->copy()->endOfDay(),
            'days' => $from->copy()->addDays($this->validity_days ?: 365)->endOfDay(),
            default => null,
        };
    }

    public function discountPercent(): int
    {
        return $this->mrp > 0 && $this->mrp > $this->price ? (int) round((1 - $this->price / $this->mrp) * 100) : 0;
    }
}
