<?php

namespace App\Modules\Tests\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestRequest extends FormRequest
{
    public function rules(): array
    {
        $c = $this->isMethod('post');

        return [
            'title' => [$c ? 'required' : 'sometimes', 'string', 'max:200'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'exam_category_id' => ['nullable', 'integer', 'exists:exam_categories,id'],
            'instructions' => ['nullable', 'string'],
            'mode' => ['nullable', 'in:online,omr'],
            'kind' => ['nullable', 'in:mock,sectional,chapter,pyq,daily_quiz,practice'],
            'access' => ['nullable', 'in:free,premium'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['in:'.implode(',', config('app.supported_languages'))],
            'duration_min' => [$c ? 'required' : 'sometimes', 'integer', 'between:1,600'],
            'sectional_timing' => ['nullable', 'boolean'],
            'shuffle' => ['nullable', 'boolean'],
            'show_result' => ['nullable', 'in:instant,after_end,manual'],
            'attempts_allowed' => ['nullable', 'integer', 'between:1,50'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sections' => ['nullable', 'array', 'max:20'],
            'sections.*.name' => ['required_with:sections', 'string', 'max:80'],
            'sections.*.short_name' => ['nullable', 'string', 'max:20'],
            'sections.*.duration_min' => ['nullable', 'integer', 'between:1,300'],
            'sections.*.marks_per_question' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sections.*.negative_per_question' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sections.*.en_only' => ['nullable', 'boolean'],
        ];
    }
}
