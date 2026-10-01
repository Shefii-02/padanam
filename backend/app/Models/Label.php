<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    protected $table = 'labels';

    protected $guarded = ['id'];

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_label');
    }
}
