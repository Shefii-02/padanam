<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMember extends Model
{
    protected $table = 'chat_members';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'can_send' => 'boolean',
            'notifications' => 'boolean',
            'pinned' => 'boolean',
            'muted_until' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
