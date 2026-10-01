<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['default_marks' => 'float', 'default_negative' => 'float'];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(QuestionFolder::class, 'folder_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'question_label');
    }

    public function testQuestions(): HasMany
    {
        return $this->hasMany(TestQuestion::class);
    }

    public function text(string $lang = 'en'): string
    {
        $t = $this->translations->firstWhere('lang', $lang) ?? $this->translations->firstWhere('lang', 'en') ?? $this->translations->first();

        return (string) $t?->text;
    }

    /** {en: "...", ml: "..."} for the app (switch language without reloading). */
    public function textMap(string $field = 'text'): array
    {
        return $this->translations->mapWithKeys(fn ($t) => [$t->lang => $t->{$field}])->filter()->all();
    }

    public function correctOptionIndexes(): array
    {
        return $this->options->values()->filter(fn ($o) => $o->is_correct)->keys()->values()->all();
    }

    public static function hashFor(string $text): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/', ' ', strip_tags($text))));
    }
}
