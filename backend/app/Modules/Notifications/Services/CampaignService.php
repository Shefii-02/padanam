<?php

namespace App\Modules\Notifications\Services;

use App\Core\Support\DomainException;
use App\Models\Device;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\NotificationCampaign;
use App\Models\User;
use App\Models\UserExamInterest;
use Illuminate\Support\Collection;

/**
 * Custom push campaigns from the admin composer.
 * audience + audience_filter:
 *   all_installs {} · all_users {} · course {course_ids[]} · batch {batch_ids[]} · category_interest {category_ids[]}
 *   role {roles[]} · custom_users {user_ids[] | phones[]} · inactive_days {days} · leads {status?, source?} · expiring {days}
 */
class CampaignService
{
    public function __construct(private Notifier $notifier) {}

    public function audience(string $type, array $f): Collection
    {
        return match ($type) {
            'all_installs' => Device::whereNotNull('fcm_token')->distinct()->pluck('user_id'),
            'all_users' => User::role('student')->where('status', 'active')->pluck('id'),
            'course' => Enrollment::whereIn('course_id', $f['course_ids'] ?? [])->active()->distinct()->pluck('user_id'),
            'batch' => Enrollment::whereIn('batch_id', $f['batch_ids'] ?? [])->active()->distinct()->pluck('user_id'),
            'category_interest' => UserExamInterest::whereIn('exam_category_id', $f['category_ids'] ?? [])->distinct()->pluck('user_id'),
            'role' => User::role($f['roles'] ?? ['student'])->pluck('id'),
            'custom_users' => User::whereIn('id', $f['user_ids'] ?? [])->orWhereIn('phone', $f['phones'] ?? [])->pluck('id'),
            'inactive_days' => User::role('student')->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays((int) ($f['days'] ?? 7))))->pluck('id'),
            'leads' => User::whereIn('phone', Lead::when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->when($f['source'] ?? null, fn ($q, $v) => $q->where('source', $v))->select('phone'))->pluck('id'),
            'expiring' => Enrollment::active()->whereBetween('expires_at', [now(), now()->addDays((int) ($f['days'] ?? 7))])->distinct()->pluck('user_id'),
            default => throw new DomainException('Unknown audience.'),
        };
    }

    public function preview(string $type, array $f): array
    {
        $ids = $this->audience($type, $f);

        return ['users' => $ids->count(), 'with_app' => Device::whereIn('user_id', $ids)->whereNotNull('fcm_token')->distinct()->count('user_id')];
    }

    public function save(array $d, ?NotificationCampaign $c = null): NotificationCampaign
    {
        if ($c && in_array($c->status, ['sending', 'sent'], true)) {
            throw new DomainException('This campaign was already sent.');
        }
        $d['status'] = ! empty($d['scheduled_at']) ? 'scheduled' : 'draft';

        return $c ? tap($c)->update($d) : NotificationCampaign::create($d + ['created_by' => auth()->id()]);
    }

    public function send(NotificationCampaign $c): NotificationCampaign
    {
        $claimed = NotificationCampaign::whereKey($c->id)->whereIn('status', ['draft', 'scheduled'])->update(['status' => 'sending']);
        if (! $claimed) {
            throw new DomainException('This campaign is already '.$c->fresh()->status.'.');
        }
        try {
            $ids = $this->audience($c->audience, $c->audience_filter ?? [])->all();
            $c->update(['target_count' => count($ids)]);
            $sent = 0;
            $failed = 0;
            foreach (array_chunk($ids, 2000) as $chunk) {
                $res = $this->notifier->raw($chunk, $c->channel_key, $c->title, $c->body, $c->deep_link, ['campaign_id' => $c->id], $c->id, $c->image);
                $sent += $res['sent'];
                $failed += $res['failed'];
            }
            $c->update(['status' => 'sent', 'sent' => $sent, 'failed' => $failed]);
        } catch (\Throwable $e) {
            $c->update(['status' => 'failed']);
            throw $e;
        }

        return $c->fresh();
    }
}
