<?php

namespace App\Modules\Notifications\Services;

use App\Models\Device;
use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/** Low-level FCM sender. Removes dead tokens automatically. */
class PushService
{
    /** @return array{sent:int, failed:int} */
    public function toUsers(array $userIds, string $channelKey, string $title, string $body, array $data = [], ?string $image = null): array
    {
        $tokens = Device::whereIn('user_id', $userIds)->whereNotNull('fcm_token')->pluck('fcm_token')->unique()->values()->all();
        if (! $tokens) {
            return ['sent' => 0, 'failed' => 0];
        }
        if (! config('services.fcm.credentials') || ! class_exists(\Kreait\Laravel\Firebase\Facades\Firebase::class)) {
            Log::info('FCM (not configured) '.$channelKey.': '.$title, ['users' => count($userIds)]);

            return ['sent' => 0, 'failed' => 0];
        }

        $channel = NotificationChannel::firstWhere('key', $channelKey);
        $isAlarm = $channelKey === 'class_alert';
        $data = array_map('strval', $data + ['channel' => $channelKey, 'title' => $title, 'body' => $body]);

        // Class alert = DATA-ONLY high-priority message: the app's background handler shows a
        // full-screen ringing notification (like an alarm) even when the app is closed.
        $message = CloudMessage::new();
        if (! $isAlarm) {
            $message = $message->withNotification(Notification::create($title, $body, $image));
        }
        $message = $message
            ->withData($data)
            ->withAndroidConfig(AndroidConfig::fromArray(array_filter([
                'priority' => $isAlarm || in_array($channel?->importance, ['high', 'max'], true) ? 'high' : 'normal',
                'ttl' => $isAlarm ? '300s' : '86400s',
                'notification' => $isAlarm ? null : array_filter([
                    'channel_id' => $channelKey,           // Flutter creates the same channel ids
                    'sound' => $channel?->sound ?? 'default',
                    'default_vibrate_timings' => true,
                ]),
            ], fn ($v) => $v !== null)))
            ->withApnsConfig(ApnsConfig::fromArray([
                'headers' => ['apns-priority' => $isAlarm ? '10' : '5'],
                // iOS has no full-screen intents: the alert is a time-sensitive notification with a long sound
                'payload' => ['aps' => array_filter([
                    'alert' => $isAlarm ? ['title' => $title, 'body' => $body] : null,
                    'sound' => $channel?->sound ? $channel->sound.'.caf' : 'default',
                    'interruption-level' => $isAlarm ? 'time-sensitive' : 'active',
                    'content-available' => $isAlarm ? 1 : null,
                ])],
            ]));

        $sent = 0;
        $failed = 0;
        $messaging = \Kreait\Laravel\Firebase\Facades\Firebase::messaging();
        foreach (array_chunk($tokens, 500) as $chunk) {
            $report = $messaging->sendMulticast($message, $chunk);
            $sent += $report->successes()->count();
            $failed += $report->failures()->count();
            $dead = array_merge($report->invalidTokens(), $report->unknownTokens());
            if ($dead) {
                Device::whereIn('fcm_token', $dead)->delete();
            }
        }

        return compact('sent', 'failed');
    }
}
