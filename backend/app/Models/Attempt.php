<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attempt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'section_time' => 'array',
            'score' => 'float',
            'negative' => 'float',
            'percentile' => 'float',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Attempt $a) {
            $a->uid ??= (string) \Illuminate\Support\Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
