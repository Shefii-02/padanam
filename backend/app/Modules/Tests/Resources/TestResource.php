<?php

namespace App\Modules\Tests\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'course' => $this->whenLoaded('course', fn () => $this->course?->title),
            'exam_category_id' => $this->exam_category_id,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'mode' => $this->mode,
            'kind' => $this->kind,
            'access' => $this->access,
            'languages' => $this->languages ?: ['en'],
            'total_marks' => $this->total_marks,
            'total_questions' => $this->total_questions,
            'duration_min' => $this->duration_min,
            'sectional_timing' => $this->sectional_timing,
            'shuffle' => $this->shuffle,
            'show_result' => $this->show_result,
            'attempts_allowed' => $this->attempts_allowed,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'result_published_at' => $this->result_published_at?->toIso8601String(),
            'status' => $this->status,
            'attempts_count' => $this->whenCounted('attempts'),
            'sections' => $this->whenLoaded('sections', fn () => $this->sections->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'short_name' => $s->short_name, 'sort' => $s->sort,
                'duration_min' => $s->duration_min, 'marks_per_question' => $s->marks_per_question,
                'negative_per_question' => $s->negative_per_question, 'en_only' => $s->en_only,
                'questions_count' => $s->test_questions_count ?? ($s->relationLoaded('testQuestions') ? $s->testQuestions->count() : null),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
