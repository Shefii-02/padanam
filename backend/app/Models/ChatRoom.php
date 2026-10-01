<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatRoom extends Model
{
    use SoftDeletes;

    public const DEFAULT_SETTINGS = [
        'who_can_send' => 'all',          // all | admins
        'send_media' => true,
        'send_links' => false,
        'members_can_invite' => true,
        'slow_mode_sec' => 0,
        'voice_enabled' => false,         // future voice rooms
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'invite_enabled' => 'boolean', 'last_message_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatMember::class, 'room_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('left_at');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_members', 'room_id')->withPivot('role', 'left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(ChatJoinRequest::class, 'room_id');
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    public function inviteUrl(): ?string
    {
        return $this->invite_code ? rtrim(config('app.deep_link_host'), '/').'/j/'.$this->invite_code : null;
    }
}
