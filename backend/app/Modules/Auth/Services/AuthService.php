<?php

namespace App\Modules\Auth\Services;

use App\Core\Enums\Role;
use App\Core\Support\Code;
use App\Core\Support\DomainException;
use App\Models\Device;
use App\Models\User;
use App\Modules\Auth\DTOs\DeviceData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(private OtpService $otp) {}

    /** OTP login for the app. Creates the student on first login. */
    public function loginWithOtp(string $phone, string $code, ?DeviceData $device): array
    {
        $this->otp->verify($phone, $code);

        $user = DB::transaction(function () use ($phone) {
            $user = User::withTrashed()->firstWhere('phone', $phone);
            if ($user?->trashed()) {
                $user->restore();
            }
            if (! $user) {
                $user = User::create([
                    'phone' => $phone,
                    'is_new_user' => true,
                    'referral_code' => Code::make(8),
                ]);
                $user->assignRole(Role::Student->value);
            }

            return $user;
        });

        if ($user->status === 'blocked') {
            throw new DomainException('Your account is blocked. Please contact support.', 403);
        }

        return $this->issue($user, $device);
    }

    /** Email + password login for the admin panel. */
    public function loginWithPassword(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();
        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            throw new DomainException('Email or password is incorrect.', 422);
        }
        if (! $user->isPanelUser()) {
            throw new DomainException('This login is for staff. Students use the app.', 403);
        }
        if ($user->status === 'blocked') {
            throw new DomainException('Your account is blocked.', 403);
        }

        return $this->issue($user, null, 'web');
    }

    public function issue(User $user, ?DeviceData $device, string $platform = 'app'): array
    {
        if ($device) {
            $this->registerDevice($user, $device);
        }
        DB::table('login_activities')->insert([
            'user_id' => $user->id, 'ip' => request()->ip(), 'platform' => $device?->platform ?? $platform,
            'user_agent' => substr((string) request()->userAgent(), 0, 250), 'at' => now(),
        ]);
        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return [
            'token' => JWTAuth::fromUser($user),
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => $user->fresh(['roles', 'profile']),
            'is_new_user' => $user->is_new_user,
        ];
    }

    public function registerDevice(User $user, DeviceData $d): Device
    {
        if ($d->fcm_token) {
            // a token belongs to one user only
            Device::where('fcm_token', $d->fcm_token)->where('user_id', '!=', $user->id)->delete();
        }

        return Device::updateOrCreate(
            ['user_id' => $user->id, 'fcm_token' => $d->fcm_token],
            ['platform' => $d->platform, 'app_build' => $d->app_build, 'device_name' => $d->device_name, 'last_active_at' => now()]
        );
    }

    public function refresh(): array
    {
        return ['token' => JWTAuth::parseToken()->refresh(), 'token_type' => 'Bearer', 'expires_in' => config('jwt.ttl') * 60];
    }

    public function logout(?string $fcmToken): void
    {
        if ($fcmToken && auth()->id()) {
            Device::where('user_id', auth()->id())->where('fcm_token', $fcmToken)->delete();
        }
        JWTAuth::parseToken()->invalidate();
    }
}
