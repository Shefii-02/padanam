<?php

namespace App\Modules\Auth\Services;

use App\Core\Support\DomainException;
use App\Models\OtpCode;
use App\Models\OtpLog;
use App\Models\User;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Login OTP. Delivery: WhatsApp API when "WhatsApp OTP" is ON (Settings → WhatsApp → OTP),
 * otherwise SMS. Every send / verify is written to otp_logs (Admin → Security → OTP logs).
 */
class OtpService
{
    public const TTL_MIN = 5;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SEC = 30;

    public function __construct(private WhatsAppService $whatsapp) {}

    /** @return array{resend_in:int, expires_in:int, channel:string} */
    public function send(string $phone): array
    {
        $key = 'otp:'.$phone;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->log($phone, 'log', 'rate_limited', 'Too many requests');
            throw new DomainException('Too many OTP requests. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' min.', 429);
        }
        if (User::where('phone', $phone)->where('status', 'blocked')->exists()) {
            $this->log($phone, 'log', 'blocked', 'Account blocked');
            throw new DomainException('Your account is blocked. Please contact support.', 403);
        }
        RateLimiter::hit($key, 3600);

        $code = app()->isProduction() ? (string) random_int(100000, 999999) : (string) config('app.demo_otp');
        OtpCode::where('phone', $phone)->whereNull('used_at')->delete();
        OtpCode::create(['phone' => $phone, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(self::TTL_MIN)]);

        $channel = $this->deliver($phone, $code);

        return ['resend_in' => self::RESEND_SEC, 'expires_in' => self::TTL_MIN * 60, 'channel' => $channel];
    }

    public function verify(string $phone, string $code): void
    {
        $otp = OtpCode::where('phone', $phone)->whereNull('used_at')->latest('id')->first();
        $log = OtpLog::where('phone', $phone)->where('status', 'sent')->latest('id')->first();
        if (! $otp || $otp->expires_at->isPast()) {
            $log?->update(['status' => 'expired']);
            throw new DomainException('The OTP has expired. Tap “Resend OTP”.', 422);
        }
        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            throw new DomainException('Too many wrong attempts. Request a new OTP.', 429);
        }
        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            $log?->increment('attempts');
            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                $log?->update(['status' => 'wrong_code', 'error' => 'Max wrong attempts']);
            }
            $left = self::MAX_ATTEMPTS - $otp->attempts;
            throw new DomainException("Incorrect OTP. $left attempt".($left === 1 ? '' : 's').' left.', 422, ['attempts_left' => $left]);
        }
        $otp->update(['used_at' => now()]);
        $log?->update(['status' => 'verified', 'verified_at' => now(), 'user_id' => User::where('phone', $phone)->value('id')]);
    }

    /** Returns the channel used: whatsapp | sms | log */
    protected function deliver(string $phone, string $code): string
    {
        $acc = $this->whatsapp->account('otp');
        if ($acc->isReady()) {
            $msg = $this->whatsapp->sendOtp($phone, $code);
            if ($msg->status === 'sent') {
                $this->log($phone, 'whatsapp', 'sent');

                return 'whatsapp';
            }
            $this->log($phone, 'whatsapp', 'failed', $msg->error);
            if (! $acc->fallback_sms) {
                throw new DomainException('Could not send the OTP on WhatsApp. Please try again.', 503);
            }
        }

        return $this->sms($phone, $code);
    }

    protected function sms(string $phone, string $code): string
    {
        // Plug MSG91 / 2Factor here (config services.sms). Log driver for local.
        if (config('services.sms.driver', 'log') === 'log') {
            Log::info("OTP for $phone: $code");
            $this->log($phone, 'log', 'sent');

            return 'log';
        }
        try {
            // SmsGateway::send($phone, "$code is your Padanam OTP");
            $this->log($phone, 'sms', 'sent');
        } catch (\Throwable $e) {
            $this->log($phone, 'sms', 'failed', $e->getMessage());
            throw new DomainException('Could not send the OTP. Please try again.', 503);
        }

        return 'sms';
    }

    private function log(string $phone, string $channel, string $status, ?string $error = null): void
    {
        $r = request();
        OtpLog::create([
            'phone' => $phone, 'channel' => $channel, 'status' => $status, 'error' => $error ? mb_substr($error, 0, 300) : null,
            'user_id' => User::where('phone', $phone)->value('id'),
            'ip' => $r?->ip(), 'user_agent' => mb_substr((string) $r?->userAgent(), 0, 250), 'platform' => $r?->header('X-Platform') ?: $r?->input('platform'),
        ]);
    }
}
