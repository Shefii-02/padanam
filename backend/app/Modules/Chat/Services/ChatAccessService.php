<?php

namespace App\Modules\Chat\Services;

use App\Models\Batch;
use App\Models\ChatMember;
use App\Models\ChatPolicy;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;

/** Who may message whom, and who may manage a group. */
class ChatAccessService
{
    /** Direct chat rule from the admin matrix (chat_policies). */
    public function canDirect(User $from, User $to): bool
    {
        if ($from->id === $to->id || $to->status === 'blocked') {
            return false;
        }
        if (! $from->can('chat.use')) {
            return false;
        }
        $fr = $from->primaryRole();
        $tr = $to->primaryRole();
        $rule = ChatPolicy::where('from_role', $fr)->whereIn('to_role', [$tr, '*'])->orderByRaw("to_role = '*'")->value('rule') ?? 'deny';

        return match ($rule) {
            'allow' => true,
            'shared_batch' => $this->shareBatch($from, $to),
            'support_only' => $to->isPanelUser() && $to->can('chat.moderate'),
            default => false,
        };
    }

    /** Student ↔ teacher of one of the student's active batches (or the course). */
    public function shareBatch(User $a, User $b): bool
    {
        [$student, $teacher] = $a->isPanelUser() ? [$b, $a] : [$a, $b];
        $batchIds = Enrollment::where('user_id', $student->id)->active()->pluck('batch_id');
        if ($batchIds->isEmpty()) {
            return false;
        }
        $courseIds = Batch::whereIn('id', $batchIds)->pluck('course_id');

        return \Illuminate\Support\Facades\DB::table('batch_staff')->whereIn('batch_id', $batchIds)->where('user_id', $teacher->id)->exists()
            || \Illuminate\Support\Facades\DB::table('course_staff')->whereIn('course_id', $courseIds)->where('user_id', $teacher->id)->exists();
    }

    public function member(ChatRoom $room, User $u): ?ChatMember
    {
        return ChatMember::where('room_id', $room->id)->where('user_id', $u->id)->whereNull('left_at')->first();
    }

    /** Group owner/admin inside the room, or staff with chat.manage_groups. */
    public function canManage(ChatRoom $room, User $u): bool
    {
        if ($u->can('chat.manage_groups')) {
            return true;
        }
        $m = $this->member($room, $u);

        return $m && in_array($m->role, ['owner', 'admin'], true);
    }

    public function canModerate(ChatRoom $room, User $u): bool
    {
        if ($u->can('chat.moderate') && ($u->isAdmin() || ! $room->batch_id || $room->batch->course->isManagedBy($u))) {
            return true;
        }
        $m = $this->member($room, $u);

        return $m && in_array($m->role, ['owner', 'admin', 'moderator'], true);
    }
}
