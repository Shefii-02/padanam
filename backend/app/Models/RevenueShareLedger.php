<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueShareLedger extends Model
{
    protected $table = 'revenue_share_ledger';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'percent' => 'float',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
