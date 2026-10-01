<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Video extends Model
{
    protected $table = 'videos';

    protected $guarded = ['id'];

    public function content(): MorphOne
    {
        return $this->morphOne(Content::class, 'contentable');
    }
}
