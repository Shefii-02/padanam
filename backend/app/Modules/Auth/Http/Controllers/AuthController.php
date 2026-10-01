<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Modules\Auth\DTOs\DeviceData;
use App\Modules\Auth\Http\Requests\PasswordLoginRequest;
use App\Modules\Auth\Http\Requests\SendOtpRequest;
use App\Modules\Auth\Http\Requests\VerifyOtpRequest;
use App\Modules\Auth\Resources\MeResource;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\OtpService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth, private OtpService $otp) {}

    public function sendOtp(SendOtpRequest $r)
    {
        $phone = $r->validated('phone');
        $info = $this->otp->send($phone);

        return ApiResponse::ok($info + ['phone' => $phone, 'is_registered' => \App\Models\User::where('phone', $phone)->exists()],
            $info['channel'] === 'whatsapp' ? 'OTP sent on WhatsApp' : 'OTP sent by SMS');
    }

    /** Lets the app show "OTP will come on WhatsApp" before sending. */
    public function otpChannel()
    {
        $wa = \App\Models\WhatsAppAccount::for('otp');

        return ApiResponse::ok(['channel' => $wa?->isReady() ? 'whatsapp' : 'sms']);
    }

    public function verifyOtp(VerifyOtpRequest $r)
    {
        $out = $this->auth->loginWithOtp($r->validated('phone'), $r->validated('otp'), DeviceData::optional($r));

        return ApiResponse::ok($this->present($out), $out['is_new_user'] ? 'Welcome to Padanam' : 'Welcome back');
    }

    public function passwordLogin(PasswordLoginRequest $r)
    {
        $out = $this->auth->loginWithPassword($r->validated('email'), $r->validated('password'));

        return ApiResponse::ok($this->present($out), 'Logged in');
    }

    public function me(Request $r)
    {
        return ApiResponse::ok(new MeResource($r->user()->load('roles', 'profile', 'managedCourses:id')));
    }

    public function refresh()
    {
        return ApiResponse::ok($this->auth->refresh());
    }

    public function logout(Request $r)
    {
        $this->auth->logout($r->input('fcm_token'));

        return ApiResponse::ok(null, 'Logged out');
    }

    public function device(Request $r)
    {
        $r->validate(['platform' => 'required|in:android,ios,web,windows,macos,linux', 'fcm_token' => 'nullable|string|max:255']);
        $this->auth->registerDevice($r->user(), DeviceData::fromRequest($r));

        return ApiResponse::ok(null, 'Device saved');
    }

    private function present(array $out): array
    {
        $out['user'] = (new MeResource($out['user']->load('managedCourses:id')))->resolve();

        return $out;
    }
}
