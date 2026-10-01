<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileSetupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'avatar' => ['nullable', 'string', 'max:16'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date', 'before:-14 years', 'after:-70 years'],
            'district' => ['nullable', 'string', 'max:60'],
            'state' => ['nullable', 'string', 'max:60'],
            'town' => ['nullable', 'string', 'max:80'],
            'pincode' => ['nullable', 'digits:6'],
            'qualification' => ['nullable', 'string', 'max:40'],
            'exams' => ['required', 'array', 'min:1', 'max:3'],
            'target_posts' => ['nullable', 'array'],
            'level' => ['nullable', 'string', 'max:40'],
            'aim' => ['nullable', 'string', 'max:40'],
            'attempt' => ['nullable', 'string', 'max:40'],
            'study_hours' => ['nullable', 'integer', 'between:1,12'],
            'study_days' => ['nullable', 'array', 'size:7'],
            'study_slot' => ['nullable', 'string', 'max:20'],
            'reminder' => ['nullable', 'boolean'],
            'language' => ['nullable', 'in:ml,en,both'],
        ];
    }

    public function messages(): array
    {
        return ['dob.before' => 'You must be at least 14 years old.', 'exams.max' => 'Pick up to 3 exams.'];
    }
}
