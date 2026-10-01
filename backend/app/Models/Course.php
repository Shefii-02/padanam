<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use SoftDeletes;

    /** Feature switches → which tabs students see. */
    public const FEATURES = [
        'live_classes' => 'Live classes',
        'recordings' => 'Recordings',
        'premium_tests' => 'Premium tests',
        'free_tests' => 'Free tests',
        'demo_videos' => 'Free demo videos',
        'free_notes' => 'Free notes',
        'premium_notes' => 'Premium notes',
        'articles' => 'Articles',
        'daily_quiz' => 'Daily quiz',
        'study_plan' => 'Study plan',
        'doubts' => 'Doubts',
        'chat_group' => 'Batch chat group',
    ];

    /** Presets per course type (admin can still change each switch). */
    public const TYPE_PRESETS = [
        'live_recorded' => ['*'],
        'live_only' => ['live_classes', 'free_notes', 'premium_notes', 'doubts', 'chat_group', 'daily_quiz'],
        'recorded_only' => ['recordings', 'demo_videos', 'free_notes', 'premium_notes', 'free_tests', 'premium_tests', 'doubts'],
        'tests_only' => ['free_tests', 'premium_tests', 'daily_quiz'],
        'material_only' => ['free_notes', 'premium_notes', 'articles'],
        'custom' => [],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'what_you_get' => 'array',
            'is_featured' => 'boolean',
            'rating' => 'float',
            'published_at' => 'datetime',
        ];
    }

    public static function presetFeatures(string $type): array
    {
        $on = self::TYPE_PRESETS[$type] ?? ['*'];
        $out = [];
        foreach (array_keys(self::FEATURES) as $k) {
            $out[$k] = $on === ['*'] || in_array($k, $on, true);
        }

        return $out;
    }

    public function feature(string $key): bool
    {
        return (bool) ($this->features[$key] ?? false);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExamCategory::class, 'exam_category_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class)->orderBy('sort');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(CourseFolder::class)->orderBy('sort');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class)->orderBy('sort');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_staff')->withPivot('role')->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    /** Courses a panel user may see: admins/all-scope see everything, others only assigned ones. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isAdmin() || $user->can('courses.all_scope')) {
            return $q;
        }

        return $q->whereHas('staff', fn ($s) => $s->where('users.id', $user->id));
    }

    public function isManagedBy(User $user): bool
    {
        if ($user->isAdmin() || $user->can('courses.all_scope')) {
            return true;
        }

        return $this->staff()->where('users.id', $user->id)->exists();
    }

    public function staffRole(User $user): ?string
    {
        return $this->staff()->where('users.id', $user->id)->first()?->pivot?->role;
    }
}
