<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    protected $table = 'staff_profiles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subjects' => 'array',
            'is_teacher' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
