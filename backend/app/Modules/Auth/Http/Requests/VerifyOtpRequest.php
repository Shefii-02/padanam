<?php

namespace App\Modules\Auth\Http\Requests;

class VerifyOtpRequest extends SendOtpRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'otp' => ['required', 'digits:6'],
            'device.platform' => ['nullable', 'in:android,ios,web,windows,macos,linux'],
            'device.fcm_token' => ['nullable', 'string', 'max:255'],
            'device.app_build' => ['nullable', 'integer'],
        ];
    }
}
