<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\NotificationCampaign;
use App\Models\NotificationChannel;
use App\Models\NotificationTemplate;
use App\Modules\Notifications\Services\CampaignService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(private CampaignService $campaigns) {}

    public function index()
    {
        return ApiResponse::ok(NotificationCampaign::with('creator:id,name')->latest('id')->paginate(30));
    }

    public function channels()
    {
        return ApiResponse::ok(NotificationChannel::orderBy('id')->get());
    }

    public function preview(Request $r)
    {
        $d = $r->validate(['audience' => 'required|string', 'audience_filter' => 'nullable|array']);

        return ApiResponse::ok($this->campaigns->preview($d['audience'], $d['audience_filter'] ?? []));
    }

    public function store(Request $r)
    {
        $d = $this->rules($r);
        $this->guardAll($r, $d['audience']);
        $c = $this->campaigns->save($d);
        if ($r->boolean('send_now')) {
            $c = $this->campaigns->send($c);

            return ApiResponse::created($c, "Sent to {$c->sent} devices");
        }

        return ApiResponse::created($c, $c->status === 'scheduled' ? 'Scheduled' : 'Saved as draft');
    }

    public function update(Request $r, NotificationCampaign $campaign)
    {
        $d = $this->rules($r);
        $this->guardAll($r, $d['audience']);

        return ApiResponse::ok($this->campaigns->save($d, $campaign), 'Saved');
    }

    public function send(Request $r, NotificationCampaign $campaign)
    {
        $this->guardAll($r, $campaign->audience);
        $c = $this->campaigns->send($campaign);

        return ApiResponse::ok($c, "Sent to {$c->sent} devices");
    }

    public function cancel(NotificationCampaign $campaign)
    {
        abort_unless(in_array($campaign->status, ['draft', 'scheduled'], true), 422, 'Already sent.');
        $campaign->update(['status' => 'cancelled']);

        return ApiResponse::ok($campaign, 'Cancelled');
    }

    public function templates()
    {
        return ApiResponse::ok(NotificationTemplate::orderBy('key')->get());
    }

    public function updateTemplate(Request $r, NotificationTemplate $template)
    {
        $d = $r->validate(['title' => 'required|string|max:120', 'body' => 'required|string|max:500', 'deep_link' => 'nullable|string|max:200']);
        $template->update($d);

        return ApiResponse::ok($template, 'Template saved');
    }

    private function rules(Request $r): array
    {
        $d = $r->validate([
            'title' => 'required|string|max:120', 'body' => 'required|string|max:500', 'image' => 'nullable|url', 'deep_link' => 'nullable|string|max:200',
            'channel_key' => 'required|exists:notification_channels,key',
            'audience' => 'required|in:all_installs,all_users,course,batch,category_interest,role,custom_users,inactive_days,leads,expiring',
            'audience_filter' => 'nullable|array', 'scheduled_at' => 'nullable|date|after:now',
        ]);
        abort_if($d['channel_key'] === 'class_alert', 422, 'The class alert ring is only for live classes.');

        return $d;
    }

    /** Sending to everyone needs a separate permission (teachers can message their own batches). */
    private function guardAll(Request $r, string $audience): void
    {
        if (in_array($audience, ['all_installs', 'all_users', 'category_interest', 'inactive_days', 'leads', 'role', 'expiring'], true)) {
            abort_unless($r->user()->can('notifications.send_all'), 403, 'You can only send to your own courses and batches.');
        }
        $u = $r->user();
        if (in_array($audience, ['course', 'batch'], true) && ! $u->isAdmin() && ! $u->can('courses.all_scope')) {
            $f = (array) $r->input('audience_filter', []);
            $courseIds = $audience === 'course' ? ($f['course_ids'] ?? []) : \App\Models\Batch::whereIn('id', $f['batch_ids'] ?? [])->pluck('course_id')->all();
            $mine = $u->managedCourses()->pluck('courses.id')->all();
            abort_if(array_diff($courseIds, $mine), 403, 'You can only send to your own courses and batches.');
        }
    }
}
