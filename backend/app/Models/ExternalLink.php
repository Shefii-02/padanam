<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ExternalLink extends Model
{
    protected $table = 'external_links';

    protected $guarded = ['id'];

    public function content(): MorphOne
    {
        return $this->morphOne(Content::class, 'contentable');
    }
}
