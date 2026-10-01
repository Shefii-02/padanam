<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    protected $table = 'notification_channels';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_can_disable' => 'boolean',
        ];
    }
}
