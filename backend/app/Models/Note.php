<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Note extends Model
{
    protected $table = 'notes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'tip' => 'array',
            'one_liners' => 'array',
        ];
    }

    public function content(): MorphOne
    {
        return $this->morphOne(Content::class, 'contentable');
    }
}
