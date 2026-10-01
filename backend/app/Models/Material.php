<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Material extends Model
{
    protected $table = 'materials';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'downloadable' => 'boolean',
        ];
    }

    public function content(): MorphOne
    {
        return $this->morphOne(Content::class, 'contentable');
    }
}
