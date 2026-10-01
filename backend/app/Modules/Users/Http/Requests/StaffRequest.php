<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('user')?->id;
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:80'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'regex:/^[6-9]\d{9}$/', Rule::unique('users', 'phone')->ignore($id)],
            'password' => [$creating ? 'required' : 'sometimes', 'string', 'min:8'],
            'role' => [$creating ? 'required' : 'sometimes', Rule::exists('roles', 'name')->where('guard_name', 'api'), 'not_in:student'],
            'designation' => ['nullable', 'string', 'max:60'],
            'subjects' => ['nullable', 'array'],
            'is_teacher' => ['nullable', 'boolean'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ];
    }
}
