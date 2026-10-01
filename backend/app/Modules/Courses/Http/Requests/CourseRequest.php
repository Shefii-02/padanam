<?php

namespace App\Modules\Courses\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
{
    public function rules(): array
    {
        $c = $this->isMethod('post');

        return [
            'title' => [$c ? 'required' : 'sometimes', 'string', 'min:3', 'max:160'],
            'exam_category_id' => ['nullable', 'integer', 'exists:exam_categories,id'],
            'exam_id' => ['nullable', 'integer', 'exists:exams,id'],
            'course_type' => ['nullable', Rule::in(array_keys(Course::TYPE_PRESETS))],
            'language' => ['nullable', 'string', 'max:20'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'intro_video_url' => ['nullable', 'url'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'what_you_get' => ['nullable', 'array'],
            'level' => ['nullable', 'string', 'max:40'],
            'features' => ['nullable', 'array'],
            'features.*' => ['boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:500000'],
            'mrp' => ['nullable', 'numeric', 'min:0', 'max:500000', 'gte:price'],
            'staff' => ['nullable', 'array'],
            'staff.*.user_id' => ['required_with:staff', 'integer', 'exists:users,id'],
            'staff.*.role' => ['nullable', 'in:manager,teacher'],
        ];
    }
}
