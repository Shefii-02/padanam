<?php

namespace App\Modules\Leads\Services;

use App\Models\ActivityEvent;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;

/**
 * Leads come in automatically: people who watch demos, tap offers, open course pages, take free tests –
 * but haven't bought. Each signal raises the interest score so the sales team calls the hottest first.
 */
class LeadService
{
    public const SCORES = ['demo_video' => 15, 'launch_offer' => 25, 'course_page' => 5, 'free_test' => 10, 'app_install' => 2, 'whatsapp' => 20, 'website' => 5, 'manual' => 0];

    /** App tracking endpoint → activity event (+ lead for buying signals). */
    public function track(User $user, string $event, array $props, ?string $platform): void
    {
        ActivityEvent::create(['user_id' => $user->id, 'event' => $event, 'properties' => $props, 'platform' => $platform, 'at' => now()]);
        $source = match ($event) {
            'demo_play' => 'demo_video', 'offer_click' => 'launch_offer', 'course_view' => 'course_page', 'free_test_start' => 'free_test', default => null,
        };
        if ($source) {
            $this->capture($user->phone, $user->name, $source, $props['course_id'] ?? null, $props['category_id'] ?? null, $user);
        }
    }

    public function capture(?string $phone, ?string $name, string $source, ?int $courseId = null, ?int $categoryId = null, ?User $user = null): ?Lead
    {
        if (! $phone) {
            return null;
        }
        // already a paying student of that course → not a lead
        if ($user && $courseId && Enrollment::where('user_id', $user->id)->where('course_id', $courseId)->active()->exists()) {
            return null;
        }
        $lead = Lead::firstOrNew(['phone' => $phone, 'source' => $source, 'interested_course_id' => $courseId]);
        $lead->fill([
            'user_id' => $user?->id ?? $lead->user_id, 'name' => $lead->name ?: $name, 'district' => $lead->district ?: $user?->district,
            'interested_category_id' => $lead->interested_category_id ?: $categoryId,
            'score' => min(1000, (int) $lead->score + (self::SCORES[$source] ?? 1)),
        ]);
        $lead->status ??= 'new';
        if ($lead->status === 'lost') {
            $lead->status = 'new';   // came back
        }
        $lead->save();

        return $lead;
    }

    public function update(Lead $lead, array $d, User $by): Lead
    {
        if (isset($d['status']) && $d['status'] !== $lead->status) {
            LeadActivity::create(['lead_id' => $lead->id, 'type' => 'status_change', 'note' => $lead->status.' → '.$d['status'], 'by' => $by->id]);
            if ($d['status'] === 'converted') {
                $d['converted_at'] = now();
            }
        }
        $lead->update($d);

        return $lead->fresh(['assignee:id,name', 'course:id,title', 'category:id,name']);
    }

    public function log(Lead $lead, string $type, ?string $note, User $by): LeadActivity
    {
        if (in_array($type, ['call', 'whatsapp'], true)) {
            $lead->update(['last_contacted_at' => now(), 'status' => $lead->status === 'new' ? 'contacted' : $lead->status]);
        }

        return LeadActivity::create(['lead_id' => $lead->id, 'type' => $type, 'note' => $note, 'by' => $by->id]);
    }
}
