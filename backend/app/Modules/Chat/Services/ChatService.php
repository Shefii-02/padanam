<?php

namespace App\Modules\Chat\Services;

use App\Core\Support\Code;
use App\Core\Support\DomainException;
use App\Models\ChatJoinRequest;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ChatService
{
    public function __construct(private ChatAccessService $access) {}

    /** My chats with last message and unread count. */
    public function rooms(User $user): array
    {
        $members = ChatMember::where('user_id', $user->id)->whereNull('left_at')->with('room')->get()->filter(fn ($m) => $m->room);
        $roomIds = $members->pluck('room_id');
        $last = ChatMessage::whereIn('id', $members->pluck('room.last_message_id')->filter())->with('user:id,name')->get()->keyBy('room_id');
        $unread = ChatMessage::whereIn('room_id', $roomIds)->where('user_id', '!=', $user->id)
            ->where(function ($q) use ($members) {
                foreach ($members as $m) {
                    $q->orWhere(fn ($x) => $x->where('room_id', $m->room_id)->where('id', '>', (int) $m->last_read_message_id));
                }
            })->groupBy('room_id')->selectRaw('room_id, count(*) c')->pluck('c', 'room_id');

        // for direct rooms show the other person
        $directOthers = ChatMember::whereIn('room_id', $members->where('room.type', 'direct')->pluck('room_id'))
            ->where('user_id', '!=', $user->id)->with('user:id,name,avatar,photo,last_seen_at')->get()->keyBy('room_id');

        return $members->map(function ($m) use ($last, $unread, $directOthers) {
            $r = $m->room;
            $other = $directOthers[$r->id]->user ?? null;
            $msg = $last[$r->id] ?? null;

            return [
                'id' => $r->id,
                'type' => $r->type,
                'name' => $r->type === 'direct' ? ($other?->name ?? 'Chat') : $r->name,
                'avatar' => $r->type === 'direct' ? ($other?->avatar ?? $other?->initials()) : $r->avatar,
                'other_user_id' => $other?->id,
                'members_count' => $r->members_count,
                'my_role' => $m->role,
                'muted' => ! $m->notifications,
                'pinned' => $m->pinned,
                'unread' => (int) ($unread[$r->id] ?? 0),
                'last_message' => $msg ? ['id' => $msg->id, 'type' => $msg->type, 'text' => $msg->type === 'text' ? mb_strimwidth((string) $msg->body, 0, 80, '…') : '['.$msg->type.']', 'by' => $msg->user?->name, 'at' => $msg->created_at->toIso8601String()] : null,
                'last_message_at' => $r->last_message_at?->toIso8601String(),
                'can_send' => $this->canSend($r, $m),
            ];
        })->sortByDesc(fn ($x) => [$x['pinned'], $x['last_message_at']])->values()->all();
    }

    public function canSend(ChatRoom $room, ChatMember $m): bool
    {
        if ($m->muted_until && $m->muted_until->isFuture()) {
            return false;
        }
        if ($m->can_send !== null) {
            return $m->can_send;
        }

        return $room->setting('who_can_send') === 'all' || in_array($m->role, ['owner', 'admin', 'moderator'], true);
    }

    public function openDirect(User $from, User $to): ChatRoom
    {
        if (! $this->access->canDirect($from, $to)) {
            throw new DomainException("You can't start a chat with this person.", 403);
        }
        $key = min($from->id, $to->id).':'.max($from->id, $to->id);

        return DB::transaction(function () use ($from, $to, $key) {
            $room = ChatRoom::firstOrCreate(['direct_key' => $key], ['type' => 'direct', 'settings' => ChatRoom::DEFAULT_SETTINGS + ['send_links' => true], 'join_mode' => 'invite_only', 'invite_enabled' => false, 'created_by' => $from->id]);
            foreach ([$from, $to] as $u) {
                ChatMember::updateOrCreate(['room_id' => $room->id, 'user_id' => $u->id], ['role' => 'member', 'left_at' => null, 'joined_at' => now()]);
            }
            $room->update(['members_count' => 2]);
            $this->realtime('member.added', ['room_id' => $room->id, 'user_ids' => [$from->id, $to->id]]);

            return $room;
        });
    }

    public function createGroup(User $by, array $d): ChatRoom
    {
        return DB::transaction(function () use ($by, $d) {
            $room = ChatRoom::create([
                'type' => $d['type'] ?? 'group', 'name' => $d['name'], 'avatar' => $d['avatar'] ?? '💬', 'description' => $d['description'] ?? null,
                'batch_id' => $d['batch_id'] ?? null, 'invite_code' => Code::make(8), 'join_mode' => $d['join_mode'] ?? 'approval',
                'invite_enabled' => $d['invite_enabled'] ?? true, 'settings' => array_merge(ChatRoom::DEFAULT_SETTINGS, $d['settings'] ?? []), 'created_by' => $by->id,
            ]);
            ChatMember::create(['room_id' => $room->id, 'user_id' => $by->id, 'role' => 'owner', 'joined_at' => now()]);
            $this->addMembers($room, $d['member_ids'] ?? [], 'member', $by);

            return $room->fresh();
        });
    }

    public function update(ChatRoom $room, array $d): ChatRoom
    {
        if (isset($d['settings'])) {
            $d['settings'] = array_merge($room->settings ?? [], array_intersect_key($d['settings'], ChatRoom::DEFAULT_SETTINGS));
        }
        $room->update($d);
        $this->realtime('room.updated', ['room_id' => $room->id, 'room' => $room->only(['id', 'name', 'avatar', 'description', 'settings', 'join_mode'])]);

        return $room;
    }

    public function addMembers(ChatRoom $room, array $userIds, string $role, User $by): int
    {
        $n = 0;
        foreach (array_unique($userIds) as $uid) {
            $m = ChatMember::firstOrNew(['room_id' => $room->id, 'user_id' => $uid]);
            if ($m->exists && ! $m->left_at) {
                continue;
            }
            $m->fill(['role' => $m->role && $m->role !== 'member' ? $m->role : $role, 'left_at' => null, 'joined_at' => now()])->save();
            $n++;
        }
        $room->update(['members_count' => $room->activeMembers()->count()]);
        if ($n) {
            $this->system($room, $by->name.' added '.$n.' member'.($n > 1 ? 's' : ''));
            $this->realtime('member.added', ['room_id' => $room->id, 'user_ids' => array_values($userIds)]);
        }

        return $n;
    }

    public function removeMember(ChatRoom $room, User $target, User $by): void
    {
        $m = ChatMember::where('room_id', $room->id)->where('user_id', $target->id)->firstOrFail();
        if ($m->role === 'owner' && $by->id !== $target->id && ! $by->can('chat.manage_groups')) {
            throw new DomainException("The group owner can't be removed.");
        }
        $m->update(['left_at' => now()]);
        $room->update(['members_count' => $room->activeMembers()->count()]);
        $this->system($room, $by->id === $target->id ? $target->name.' left' : $target->name.' was removed');
        $this->realtime('member.removed', ['room_id' => $room->id, 'user_id' => $target->id]);
    }

    public function setRole(ChatRoom $room, User $target, string $role): ChatMember
    {
        $m = ChatMember::where('room_id', $room->id)->where('user_id', $target->id)->whereNull('left_at')->firstOrFail();
        $m->update(['role' => $role]);
        $this->realtime('member.updated', ['room_id' => $room->id, 'user_id' => $target->id, 'role' => $role]);

        return $m;
    }

    /** minutes null = unmute, 0 = until unmuted */
    public function mute(ChatRoom $room, User $target, ?int $minutes): ChatMember
    {
        $m = ChatMember::where('room_id', $room->id)->where('user_id', $target->id)->firstOrFail();
        $m->update(['muted_until' => $minutes === null ? null : ($minutes === 0 ? now()->addYears(10) : now()->addMinutes($minutes))]);
        $this->realtime('member.updated', ['room_id' => $room->id, 'user_id' => $target->id, 'muted_until' => $m->muted_until?->toIso8601String()]);

        return $m;
    }

    // ---------- invite links ----------
    public function preview(string $code): array
    {
        $room = ChatRoom::where('invite_code', $code)->where('invite_enabled', true)->firstOrFail();

        return ['code' => $code, 'name' => $room->name, 'avatar' => $room->avatar, 'description' => $room->description,
            'members_count' => $room->members_count, 'join_mode' => $room->join_mode, 'requires_enrollment' => (bool) $room->batch_id];
    }

    /** Returns ['status' => joined|requested|already] */
    public function joinByCode(User $user, string $code): array
    {
        $room = ChatRoom::where('invite_code', $code)->where('invite_enabled', true)->first();
        if (! $room) {
            throw new DomainException('This invite link is invalid or has been reset.', 404);
        }
        if ($this->access->member($room, $user)) {
            return ['status' => 'already', 'room_id' => $room->id];
        }
        if ($room->batch_id && ! \App\Models\Enrollment::where('user_id', $user->id)->where('batch_id', $room->batch_id)->active()->exists() && ! $user->isPanelUser()) {
            throw new DomainException('This group is for students of the batch. Join the course first.', 403);
        }
        if ($room->join_mode === 'approval' && ! $user->isPanelUser()) {
            ChatJoinRequest::updateOrCreate(['room_id' => $room->id, 'user_id' => $user->id], ['status' => 'pending', 'handled_by' => null]);
            $this->realtime('join.requested', ['room_id' => $room->id, 'user' => $user->only(['id', 'name'])]);

            return ['status' => 'requested', 'room_id' => $room->id];
        }
        $this->addMembers($room, [$user->id], 'member', $user);

        return ['status' => 'joined', 'room_id' => $room->id];
    }

    public function handleRequest(ChatJoinRequest $req, bool $approve, User $by): void
    {
        $req->update(['status' => $approve ? 'approved' : 'rejected', 'handled_by' => $by->id]);
        if ($approve) {
            $this->addMembers($req->room, [$req->user_id], 'member', $by);
        }
    }

    public function resetInvite(ChatRoom $room): ChatRoom
    {
        $room->update(['invite_code' => Code::make(8)]);

        return $room;
    }

    public function deleteMessage(ChatMessage $msg, User $by): void
    {
        $msg->delete();
        $this->realtime('message.deleted', ['room_id' => $msg->room_id, 'message_id' => $msg->id, 'by' => $by->id]);
    }

    public function system(ChatRoom $room, string $text): void
    {
        $m = ChatMessage::create(['room_id' => $room->id, 'type' => 'system', 'body' => $text]);
        $room->update(['last_message_id' => $m->id, 'last_message_at' => now()]);
        $this->realtime('message.new', ['room_id' => $room->id, 'message' => ['id' => $m->id, 'type' => 'system', 'body' => $text, 'created_at' => $m->created_at->toIso8601String()]]);
    }

    /** Laravel → Node (the Node server pushes it to connected sockets). Never fails the request. */
    public function realtime(string $event, array $payload): void
    {
        $url = config('services.realtime.url');
        $key = config('services.realtime.internal_key');
        if (! $url || ! $key) {
            return;
        }
        try {
            Http::timeout(2)->withHeaders(['X-Internal-Key' => $key])->post(rtrim($url, '/').'/internal/emit', compact('event', 'payload'));
        } catch (\Throwable $e) {
            logger()->warning('realtime: '.$e->getMessage());
        }
    }
}
