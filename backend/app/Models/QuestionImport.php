<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionImport extends Model
{
    protected $table = 'question_imports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'label_ids' => 'array',
            'preview' => 'array',
            'match_translations' => 'boolean',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(QuestionFolder::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
