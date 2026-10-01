<?php

namespace App\Modules\QuestionBank\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionRequest extends FormRequest
{
    public function rules(): array
    {
        $c = $this->isMethod('post');

        return [
            'folder_id' => ['nullable', 'integer', 'exists:question_folders,id'],
            'type' => [$c ? 'required' : 'sometimes', 'in:mcq_single,mcq_multi,true_false,numeric,passage'],
            'difficulty' => ['nullable', 'in:easy,moderate,hard'],
            'subject' => ['nullable', 'string', 'max:60'],
            'topic' => ['nullable', 'string', 'max:100'],
            'default_marks' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_negative' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'numeric_answer' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'between:1950,2100'],
            'passage_id' => ['nullable', 'integer', 'exists:questions,id'],
            'translations' => [$c ? 'required' : 'sometimes', 'array'],
            'translations.*.text' => ['nullable', 'string'],
            'translations.*.solution' => ['nullable', 'string'],
            'options' => ['nullable', 'array', 'max:6'],
            'options.*.is_correct' => ['boolean'],
            'options.*.text' => ['array'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
        ];
    }
}
