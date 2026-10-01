<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionFolder extends Model
{
    protected $table = 'question_folders';

    protected $guarded = ['id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(QuestionFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(QuestionFolder::class, 'parent_id')->orderBy('sort');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'folder_id');
    }
}
