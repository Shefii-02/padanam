<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Core\Http\Controller;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Modules\Notifications\Services\PushService;
use Illuminate\Http\Request;

/** Endpoints only the Node realtime server calls. */
class InternalController extends Controller
{
    /** Push for members who are offline: {room_id, message_id, sender_id, sender_name, text, offline_user_ids[]} */
    public function notifyMessage(Request $r, PushService $push)
    {
        $d = $r->validate(['room_id' => 'required|integer', 'sender_name' => 'required|string', 'text' => 'required|string', 'offline_user_ids' => 'array', 'message_id' => 'integer']);
        $room = ChatRoom::findOrFail($d['room_id']);
        $targets = ChatMember::where('room_id', $room->id)->whereIn('user_id', $d['offline_user_ids'] ?? [])
            ->whereNull('left_at')->where('notifications', true)->pluck('user_id')->all();
        $title = $room->type === 'direct' ? $d['sender_name'] : $room->name;
        $body = $room->type === 'direct' ? $d['text'] : $d['sender_name'].': '.$d['text'];

        return response()->json($push->toUsers($targets, 'chat', $title, mb_strimwidth($body, 0, 140, '…'), [
            'room_id' => $room->id, 'message_id' => $d['message_id'] ?? 0, 'deep_link' => '/chat/'.$room->id,
        ]));
    }
}
