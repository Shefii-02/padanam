<?php

namespace App\Modules\Auth\Http\Requests;

use App\Core\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::normalize($this->input('phone')) ?? $this->input('phone')]);
    }

    public function rules(): array
    {
        return ['phone' => ['required', 'regex:/^[6-9]\d{9}$/']];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid 10-digit mobile number.'];
    }
}
