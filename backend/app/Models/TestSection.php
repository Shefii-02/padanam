<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSection extends Model
{
    protected $table = 'test_sections';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'en_only' => 'boolean',
            'marks_per_question' => 'float',
            'negative_per_question' => 'float',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function testQuestions(): HasMany
    {
        return $this->hasMany(TestQuestion::class, 'section_id')->orderBy('sort');
    }
}
