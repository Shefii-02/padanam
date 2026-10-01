<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSwap extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['keep_expiry' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'from_batch_id');
    }

    public function toBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'to_batch_id');
    }

    public function fromEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'from_enrollment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
