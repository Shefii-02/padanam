<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\AppNotification;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

/** App: notification inbox + per-channel on/off. */
class InboxController extends Controller
{
    public function index(Request $r)
    {
        $page = AppNotification::where('user_id', $r->user()->id)->latest()->paginate(30);
        $page->getCollection()->transform(fn ($n) => [
            'id' => $n->id, 'channel' => $n->channel_key, 'title' => $n->title, 'body' => $n->body, 'icon' => $n->icon,
            'deep_link' => $n->deep_link, 'data' => $n->data, 'read' => (bool) $n->read_at, 'created_at' => $n->created_at->toIso8601String(),
        ]);

        return ApiResponse::ok($page->items(), 'OK', 200, [
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
            'unread' => AppNotification::where('user_id', $r->user()->id)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $r)
    {
        $q = AppNotification::where('user_id', $r->user()->id)->whereNull('read_at');
        if ($ids = $r->input('ids')) {
            $q->whereIn('id', (array) $ids);
        }
        $q->update(['read_at' => now()]);

        return ApiResponse::ok(null, 'Marked as read');
    }

    public function opened(Request $r, AppNotification $notification)
    {
        abort_unless($notification->user_id === $r->user()->id, 404);
        if (! $notification->opened_at) {
            $notification->update(['opened_at' => now(), 'read_at' => $notification->read_at ?? now()]);
            if ($notification->campaign_id) {
                \App\Models\NotificationCampaign::whereKey($notification->campaign_id)->increment('opened');
            }
        }

        return ApiResponse::ok(null);
    }

    public function preferences(Request $r)
    {
        $prefs = NotificationPreference::where('user_id', $r->user()->id)->pluck('enabled', 'channel_key');

        return ApiResponse::ok(NotificationChannel::orderBy('id')->get()->map(fn ($c) => [
            'key' => $c->key, 'name' => $c->name, 'description' => $c->description,
            'can_disable' => $c->user_can_disable, 'enabled' => $c->user_can_disable ? (bool) ($prefs[$c->key] ?? true) : true,
        ]));
    }

    public function updatePreference(Request $r)
    {
        $d = $r->validate(['channel' => 'required|exists:notification_channels,key', 'enabled' => 'required|boolean']);
        $channel = NotificationChannel::firstWhere('key', $d['channel']);
        abort_unless($channel->user_can_disable, 422, 'This notification type cannot be turned off.');
        NotificationPreference::updateOrCreate(['user_id' => $r->user()->id, 'channel_key' => $d['channel']], ['enabled' => $d['enabled']]);

        return ApiResponse::ok(null, 'Saved');
    }
}
