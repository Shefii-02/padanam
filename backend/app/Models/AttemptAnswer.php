<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttemptAnswer extends Model
{
    protected $table = 'attempt_answers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'selected' => 'array',
            'is_marked' => 'boolean',
            'visited' => 'boolean',
            'is_correct' => 'boolean',
            'marks' => 'float',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function testQuestion(): BelongsTo
    {
        return $this->belongsTo(TestQuestion::class);
    }
}
