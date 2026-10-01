<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Test extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'total_marks' => 'float',
            'sectional_timing' => 'boolean',
            'shuffle' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'result_published_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TestSection::class)->orderBy('sort');
    }

    public function testQuestions(): HasMany
    {
        return $this->hasMany(TestQuestion::class)->orderBy('sort');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function content(): MorphOne
    {
        return $this->morphOne(Content::class, 'contentable');
    }

    public function scopePublished(\Illuminate\Database\Eloquent\Builder $q): \Illuminate\Database\Eloquent\Builder
    {
        return $q->where('status', 'published');
    }

    public function isOpen(): bool
    {
        return $this->status === 'published'
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->ends_at || $this->ends_at->isFuture());
    }

    public function resultVisible(): bool
    {
        return match ($this->show_result) {
            'instant' => true,
            'after_end' => ! $this->ends_at || $this->ends_at->isPast(),
            default => (bool) $this->result_published_at,
        };
    }

    /** Recomputes totals from sections/questions. */
    public function refreshTotals(): void
    {
        $this->loadMissing('testQuestions.section', 'testQuestions.question');
        $marks = 0.0;
        foreach ($this->testQuestions as $tq) {
            $marks += $tq->marks ?? $tq->section?->marks_per_question ?? $tq->question?->default_marks ?? 1;
        }
        $this->forceFill(['total_marks' => $marks, 'total_questions' => $this->testQuestions->count()])->save();
    }
}
