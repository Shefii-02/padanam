<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseFolder extends Model
{
    protected $table = 'course_folders';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'batch_ids' => 'array',
            'unlock_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(CourseFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(CourseFolder::class, 'parent_id')->orderBy('sort');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'folder_id')->orderBy('sort');
    }
}
