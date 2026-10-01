<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Content extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'batch_ids' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CourseFolder::class, 'folder_id');
    }

    public function contentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function unlockAfter(): BelongsTo
    {
        return $this->belongsTo(self::class, 'unlock_after_content_id');
    }

    public function scopePublishedNow(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    /** Visible to a batch (null batch_ids = all batches). */
    public function scopeForBatches(Builder $q, array $batchIds): Builder
    {
        return $q->where(function ($w) use ($batchIds) {
            $w->whereNull('batch_ids');
            foreach ($batchIds as $id) {
                $w->orWhereJsonContains('batch_ids', (int) $id);
            }
        });
    }

    public function isFreeToWatch(): bool
    {
        return in_array($this->access, ['free', 'demo'], true);
    }
}
