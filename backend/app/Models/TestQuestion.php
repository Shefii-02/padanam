<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestQuestion extends Model
{
    protected $table = 'test_questions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'marks' => 'float',
            'negative' => 'float',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TestSection::class, 'section_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
