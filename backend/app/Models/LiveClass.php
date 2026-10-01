<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveClass extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'went_live_at' => 'datetime',
            'ended_at' => 'datetime',
            'alert_sent_at' => 'datetime',
            'save_recording' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CourseFolder::class, 'folder_id');
    }

    public function content(): MorphOne
    {
        return $this->morphOne(Content::class, 'contentable');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(LiveClassAttendance::class);
    }

    public function endsAt()
    {
        return $this->starts_at->copy()->addMinutes($this->duration_min);
    }

    /** Extracts the YouTube id from watch, youtu.be, live and embed links. */
    public static function youtubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|live/|embed/|shorts/))([A-Za-z0-9_-]{6,20})~', $url, $m)) {
            return $m[1];
        }

        return preg_match('~^[A-Za-z0-9_-]{11}$~', $url) ? $url : null;
    }
}
