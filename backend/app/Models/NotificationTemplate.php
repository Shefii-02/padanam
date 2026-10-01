<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $table = 'notification_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }
}
