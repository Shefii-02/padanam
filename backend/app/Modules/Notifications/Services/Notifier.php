<?php

namespace App\Modules\Notifications\Services;

use App\Models\AppNotification;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;

/**
 * High-level: template + variables → in-app inbox rows + push, respecting each user's channel preferences.
 *   app(Notifier::class)->send([$userId], 'live.alert', ['title' => ..., 'minutes' => 5], ['live_class_id' => 9]);
 */
class Notifier
{
    public function __construct(private PushService $push) {}

    public function send(array $userIds, string $templateKey, array $vars = [], array $data = []): array
    {
        $tpl = NotificationTemplate::firstWhere('key', $templateKey);
        if (! $tpl) {
            return ['sent' => 0, 'failed' => 0];
        }

        return $this->raw($userIds, $tpl->channel_key, $this->fill($tpl->title, $vars), $this->fill($tpl->body, $vars),
            $tpl->deep_link ? $this->fill($tpl->deep_link, $vars) : null, $data);
    }

    public function raw(array $userIds, string $channelKey, string $title, string $body, ?string $deepLink = null, array $data = [], ?int $campaignId = null, ?string $image = null): array
    {
        $userIds = $this->allowed(array_values(array_unique($userIds)), $channelKey);
        if (! $userIds) {
            return ['sent' => 0, 'failed' => 0, 'recipients' => 0];
        }

        $now = now();
        foreach (array_chunk($userIds, 1000) as $chunk) {
            AppNotification::insert(array_map(fn ($id) => [
                'user_id' => $id, 'campaign_id' => $campaignId, 'channel_key' => $channelKey, 'title' => $title, 'body' => $body,
                'deep_link' => $deepLink, 'data' => json_encode($data), 'created_at' => $now, 'updated_at' => $now,
            ], $chunk));
        }

        return $this->push->toUsers($userIds, $channelKey, $title, $body, $data + array_filter(['deep_link' => $deepLink]), $image)
            + ['recipients' => count($userIds)];
    }

    /** Drops users who turned the channel off (only if the channel can be turned off). */
    private function allowed(array $userIds, string $channelKey): array
    {
        $channel = NotificationChannel::firstWhere('key', $channelKey);
        if ($channel && ! $channel->user_can_disable) {
            return $userIds;
        }
        $off = NotificationPreference::where('channel_key', $channelKey)->where('enabled', false)->whereIn('user_id', $userIds)->pluck('user_id')->all();

        return array_values(array_diff($userIds, $off));
    }

    private function fill(string $text, array $vars): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $text);
    }
}
