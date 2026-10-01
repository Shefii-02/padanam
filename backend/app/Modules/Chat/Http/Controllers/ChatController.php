<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\ChatJoinRequest;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatReport;
use App\Models\ChatRoom;
use App\Models\User;
use App\Modules\Chat\Services\ChatAccessService;
use App\Modules\Chat\Services\ChatService;
use Illuminate\Http\Request;

/**
 * REST side of chat (lists, history, groups, invites, moderation).
 * Live sending/typing/read receipts go through the Node socket server.
 */
class ChatController extends Controller
{
    public function __construct(private ChatService $chat, private ChatAccessService $access) {}

    public function rooms(Request $r)
    {
        return ApiResponse::ok($this->chat->rooms($r->user()));
    }

    public function show(Request $r, ChatRoom $room)
    {
        $m = $this->mustBeMember($r, $room);

        return ApiResponse::ok($room->only(['id', 'type', 'name', 'avatar', 'description', 'join_mode', 'invite_enabled', 'members_count', 'batch_id']) + [
            'settings' => array_merge(ChatRoom::DEFAULT_SETTINGS, $room->settings ?? []),
            'my_role' => $m?->role, 'can_send' => $m ? $this->chat->canSend($room, $m) : false,
            'can_manage' => $this->access->canManage($room, $r->user()),
            'invite_url' => $this->access->canManage($room, $r->user()) || ($room->setting('members_can_invite') && $m) ? $room->inviteUrl() : null,
            'pending_requests' => $this->access->canManage($room, $r->user()) ? $room->joinRequests()->where('status', 'pending')->count() : null,
        ]);
    }

    /** History, newest first. ?before_id= for older pages. */
    public function messages(Request $r, ChatRoom $room)
    {
        $this->mustBeMember($r, $room);
        $msgs = ChatMessage::withTrashed()->where('room_id', $room->id)
            ->when($r->integer('before_id'), fn ($q, $v) => $q->where('id', '<', $v))
            ->with('user:id,name,avatar', 'replyTo:id,body,user_id,type')->orderByDesc('id')->limit(min(100, $r->integer('limit', 40)))->get();

        return ApiResponse::ok($msgs->map(fn ($m) => [
            'id' => $m->id, 'room_id' => $m->room_id, 'type' => $m->type, 'client_id' => $m->client_id,
            'body' => $m->trashed() ? null : $m->body, 'meta' => $m->trashed() ? null : $m->meta, 'deleted' => $m->trashed(),
            'user' => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name, 'avatar' => $m->user->avatar] : null,
            'reply_to' => $m->replyTo ? ['id' => $m->replyTo->id, 'body' => mb_strimwidth((string) $m->replyTo->body, 0, 80, '…')] : null,
            'edited' => (bool) $m->edited_at, 'created_at' => $m->created_at->toIso8601String(),
        ]));
    }

    public function members(Request $r, ChatRoom $room)
    {
        $this->mustBeMember($r, $room);

        return ApiResponse::ok($room->activeMembers()->with('user:id,name,avatar,phone,last_seen_at')->orderByRaw("FIELD(role,'owner','admin','moderator','member')")->get()
            ->map(fn ($m) => ['user_id' => $m->user_id, 'name' => $m->user?->name, 'avatar' => $m->user?->avatar, 'role' => $m->role,
                'muted_until' => $m->muted_until?->toIso8601String(), 'can_send' => $m->can_send,
                'phone' => $this->access->canManage($room, $r->user()) ? $m->user?->phone : null]));
    }

    /** Upload an image/file/voice note → returns meta the socket message carries. */
    public function upload(Request $r, ChatRoom $room)
    {
        $m = $this->mustBeMember($r, $room);
        abort_unless($m && $this->chat->canSend($room, $m) && $room->setting('send_media'), 403, 'Media is off in this chat.');
        $r->validate(['file' => 'required|file|max:20480|mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,m4a,aac,mp3,ogg,opus']);
        $f = $r->file('file');
        $path = $f->store('chat/'.$room->id.'/'.now()->format('Y/m'), 'public');
        $mime = $f->getMimeType();

        return ApiResponse::ok([
            'type' => str_starts_with($mime, 'image/') ? 'image' : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'),
            'meta' => ['url' => asset('storage/'.$path), 'name' => $f->getClientOriginalName(), 'size' => $f->getSize(), 'mime' => $mime],
        ]);
    }

    public function direct(Request $r, User $user)
    {
        $room = $this->chat->openDirect($r->user(), $user);

        return ApiResponse::ok(['room_id' => $room->id]);
    }

    /** People I may message (for the "new chat" screen). */
    public function contacts(Request $r)
    {
        $me = $r->user();
        $q = User::query()->where('id', '!=', $me->id)->where('status', 'active')->whereHas('roles', fn ($x) => $x->where('name', '!=', 'student'))
            ->when($r->query('search'), fn ($w, $v) => $w->where('name', 'like', "%$v%"))->limit(50)->get(['id', 'name', 'avatar']);
        if ($me->isPanelUser() && $r->query('search')) {
            $q = $q->concat(User::role('student')->where('name', 'like', '%'.$r->query('search').'%')->limit(30)->get(['id', 'name', 'avatar']));
        }

        return ApiResponse::ok($q->filter(fn ($u) => $this->access->canDirect($me, $u))->values()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar, 'role' => $u->primaryRole()]));
    }

    public function leave(Request $r, ChatRoom $room)
    {
        abort_if($room->type === 'batch_group' && ! $r->user()->isPanelUser(), 422, "You can't leave your batch group. Mute it instead.");
        $this->chat->removeMember($room, $r->user(), $r->user());

        return ApiResponse::ok(null, 'You left the group');
    }

    public function myPrefs(Request $r, ChatRoom $room)
    {
        $d = $r->validate(['notifications' => 'sometimes|boolean', 'pinned' => 'sometimes|boolean']);
        ChatMember::where('room_id', $room->id)->where('user_id', $r->user()->id)->update($d);

        return ApiResponse::ok(null, 'Saved');
    }

    public function report(Request $r, ChatMessage $message)
    {
        $this->mustBeMember($r, $message->room);
        $d = $r->validate(['reason' => 'required|in:spam,abuse,off_topic,other']);
        ChatReport::firstOrCreate(['message_id' => $message->id, 'user_id' => $r->user()->id], ['reason' => $d['reason']]);

        return ApiResponse::ok(null, 'Reported to the moderators');
    }

    // ---------- invites (deep link /j/{code}) ----------
    public function invitePreview(string $code)
    {
        return ApiResponse::ok($this->chat->preview($code));
    }

    public function join(Request $r, string $code)
    {
        $res = $this->chat->joinByCode($r->user(), $code);

        return ApiResponse::ok($res, match ($res['status']) { 'requested' => 'Request sent. An admin will approve it.', 'already' => 'You are already in this group', default => 'Joined 🎉' });
    }

    // ---------- group management (app + admin panel) ----------
    public function createGroup(Request $r)
    {
        abort_unless($r->user()->can('chat.create_group'), 403);
        $d = $r->validate([
            'name' => 'required|string|max:80', 'avatar' => 'nullable|string|max:16', 'description' => 'nullable|string|max:500',
            'type' => 'nullable|in:group,broadcast,staff', 'batch_id' => 'nullable|integer|exists:batches,id',
            'join_mode' => 'nullable|in:open,approval,invite_only', 'settings' => 'nullable|array',
            'member_ids' => 'nullable|array|max:5000', 'member_ids.*' => 'integer|exists:users,id',
        ]);
        if (($d['type'] ?? null) === 'broadcast') {
            $d['settings'] = array_merge($d['settings'] ?? [], ['who_can_send' => 'admins']);
        }

        return ApiResponse::created($this->chat->createGroup($r->user(), $d), 'Group created');
    }

    public function updateGroup(Request $r, ChatRoom $room)
    {
        $this->mustManage($r, $room);
        $d = $r->validate([
            'name' => 'sometimes|string|max:80', 'avatar' => 'sometimes|nullable|string|max:16', 'description' => 'sometimes|nullable|string|max:500',
            'join_mode' => 'sometimes|in:open,approval,invite_only', 'invite_enabled' => 'sometimes|boolean',
            'settings' => 'sometimes|array', 'settings.who_can_send' => 'in:all,admins', 'settings.send_media' => 'boolean', 'settings.send_links' => 'boolean',
            'settings.members_can_invite' => 'boolean', 'settings.slow_mode_sec' => 'integer|between:0,3600', 'settings.voice_enabled' => 'boolean',
        ]);

        return ApiResponse::ok($this->chat->update($room, $d), 'Group settings saved');
    }

    public function resetInvite(Request $r, ChatRoom $room)
    {
        $this->mustManage($r, $room);

        return ApiResponse::ok(['invite_url' => $this->chat->resetInvite($room)->inviteUrl()], 'New invite link created. The old link no longer works');
    }

    public function addMembers(Request $r, ChatRoom $room)
    {
        $this->mustManage($r, $room);
        $d = $r->validate(['user_ids' => 'nullable|array|max:5000', 'user_ids.*' => 'integer|exists:users,id', 'batch_id' => 'nullable|integer|exists:batches,id', 'role' => 'nullable|in:member,moderator,admin']);
        $ids = $d['user_ids'] ?? [];
        if (! empty($d['batch_id'])) {     // add a whole batch
            $ids = array_merge($ids, \App\Models\Enrollment::where('batch_id', $d['batch_id'])->active()->pluck('user_id')->all());
        }
        $n = $this->chat->addMembers($room, $ids, $d['role'] ?? 'member', $r->user());

        return ApiResponse::ok(['added' => $n], "$n added");
    }

    public function removeMember(Request $r, ChatRoom $room, User $user)
    {
        $this->mustManage($r, $room);
        $this->chat->removeMember($room, $user, $r->user());

        return ApiResponse::ok(null, 'Removed');
    }

    public function setRole(Request $r, ChatRoom $room, User $user)
    {
        $this->mustManage($r, $room);
        $d = $r->validate(['role' => 'required|in:member,moderator,admin']);

        return ApiResponse::ok($this->chat->setRole($room, $user, $d['role']), 'Role updated');
    }

    public function mute(Request $r, ChatRoom $room, User $user)
    {
        abort_unless($this->access->canModerate($room, $r->user()), 403);
        $d = $r->validate(['minutes' => 'nullable|integer|min:0|max:525600']);

        return ApiResponse::ok($this->chat->mute($room, $user, array_key_exists('minutes', $d) ? $d['minutes'] : null), isset($d['minutes']) ? 'Muted' : 'Unmuted');
    }

    public function requests(Request $r, ChatRoom $room)
    {
        $this->mustManage($r, $room);

        return ApiResponse::ok($room->joinRequests()->where('status', 'pending')->with('user:id,name,phone,avatar')->latest()->get());
    }

    public function handleRequest(Request $r, ChatJoinRequest $joinRequest)
    {
        $this->mustManage($r, $joinRequest->room);
        $this->chat->handleRequest($joinRequest, $r->boolean('approve'), $r->user());

        return ApiResponse::ok(null, $r->boolean('approve') ? 'Approved' : 'Rejected');
    }

    public function deleteMessage(Request $r, ChatMessage $message)
    {
        $own = $message->user_id === $r->user()->id && $message->created_at->gt(now()->subHour());
        abort_unless($own || $this->access->canModerate($message->room, $r->user()), 403);
        $this->chat->deleteMessage($message, $r->user());

        return ApiResponse::ok(null, 'Message deleted');
    }

    public function destroy(Request $r, ChatRoom $room)
    {
        abort_unless($r->user()->can('chat.manage_groups'), 403);
        $room->delete();
        $this->chat->realtime('room.deleted', ['room_id' => $room->id]);

        return ApiResponse::ok(null, 'Group deleted');
    }

    // ---------- admin panel ----------
    public function adminRooms(Request $r)
    {
        return ApiResponse::ok(ChatRoom::where('type', '!=', 'direct')->with('batch:id,name')
            ->when($r->query('type'), fn ($w, $v) => $w->where('type', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->where('name', 'like', "%$v%"))
            ->withCount(['joinRequests as pending_requests' => fn ($q) => $q->where('status', 'pending')])
            ->orderByDesc('last_message_at')->paginate(30)->through(fn ($room) => $room->toArray() + ['invite_url' => $room->inviteUrl()]));
    }

    public function reports(Request $r)
    {
        return ApiResponse::ok(ChatReport::where('status', 'open')->with('message.user:id,name', 'message.room:id,name', 'user:id,name')->latest()->paginate(30));
    }

    public function handleReport(Request $r, ChatReport $report)
    {
        $d = $r->validate(['action' => 'required|in:dismiss,delete_message,mute_user', 'minutes' => 'nullable|integer|min:0']);
        if ($d['action'] === 'delete_message') {
            $this->chat->deleteMessage($report->message, $r->user());
        }
        if ($d['action'] === 'mute_user' && $report->message->user) {
            $this->chat->mute($report->message->room, $report->message->user, $d['minutes'] ?? 1440);
        }
        $report->update(['status' => $d['action'] === 'dismiss' ? 'dismissed' : 'actioned']);

        return ApiResponse::ok(null, 'Done');
    }

    public function policies()
    {
        return ApiResponse::ok(\App\Models\ChatPolicy::orderBy('from_role')->get(['from_role', 'to_role', 'rule']));
    }

    public function savePolicies(Request $r)
    {
        abort_unless($r->user()->can('chat.manage_permissions'), 403);
        $d = $r->validate(['rules' => 'required|array', 'rules.*.from_role' => 'required|string', 'rules.*.to_role' => 'required|string', 'rules.*.rule' => 'required|in:allow,deny,shared_batch,support_only']);
        foreach ($d['rules'] as $rule) {
            \App\Models\ChatPolicy::updateOrCreate(['from_role' => $rule['from_role'], 'to_role' => $rule['to_role']], ['rule' => $rule['rule']]);
        }

        return ApiResponse::ok($this->policies()->getData(true)['data'], 'Chat rules saved');
    }

    // ---------- helpers ----------
    private function mustBeMember(Request $r, ChatRoom $room): ?ChatMember
    {
        $m = $this->access->member($room, $r->user());
        abort_unless($m || $r->user()->can('chat.moderate') && $this->access->canModerate($room, $r->user()), 403, 'You are not in this chat.');

        return $m;
    }

    private function mustManage(Request $r, ChatRoom $room): void
    {
        abort_unless($this->access->canManage($room, $r->user()), 403, 'Only group admins can do this.');
    }
}
