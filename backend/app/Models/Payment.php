<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function revenueShare(): HasOne
    {
        return $this->hasOne(RevenueShareLedger::class, 'payment_id');
    }
}
