<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationCampaign extends Model
{
    protected $table = 'notification_campaigns';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'audience_filter' => 'array',
            'scheduled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
