<?php

namespace App\Modules\QuestionBank\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Admin: full question with all languages and the answer. */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder' => $this->whenLoaded('folder', fn () => $this->folder ? ['id' => $this->folder->id, 'name' => $this->folder->name] : null),
            'folder_id' => $this->folder_id,
            'type' => $this->type,
            'difficulty' => $this->difficulty,
            'subject' => $this->subject,
            'topic' => $this->topic,
            'default_marks' => $this->default_marks,
            'default_negative' => $this->default_negative,
            'numeric_answer' => $this->numeric_answer,
            'source' => $this->source,
            'year' => $this->year,
            'languages' => $this->whenLoaded('translations', fn () => $this->translations->pluck('lang')),
            'translations' => $this->whenLoaded('translations', fn () => $this->translations->mapWithKeys(fn ($t) => [$t->lang => ['text' => $t->text, 'solution' => $t->solution]])),
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($o) => [
                'id' => $o->id,
                'is_correct' => $o->is_correct,
                'text' => $o->relationLoaded('translations') ? $o->translations->pluck('text', 'lang') : [],
            ])),
            'labels' => $this->whenLoaded('labels', fn () => $this->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])),
            'used_in_tests' => $this->test_questions_count ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
