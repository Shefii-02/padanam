<?php

namespace App\Modules\Courses\Services;

use App\Core\Support\DomainException;
use App\Core\Support\VideoUrl;
use App\Models\Content;
use App\Models\ContentProgress;
use App\Models\Course;
use App\Models\CourseFolder;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\User;
use App\Modules\Courses\Resources\BatchResource;
use Illuminate\Support\Collection;

/** Everything the student app needs to browse and learn a course. */
class StudentCourseService
{
    /** App tabs → content types, shown only when the course feature switch is on. */
    public const TABS = [
        'classes' => ['label' => 'Classes', 'types' => ['video'], 'features' => ['recordings', 'demo_videos']],
        'live' => ['label' => 'Live', 'types' => ['live'], 'features' => ['live_classes']],
        'tests' => ['label' => 'Tests', 'types' => ['test', 'quiz'], 'features' => ['premium_tests', 'free_tests']],
        'notes' => ['label' => 'Notes', 'types' => ['note', 'pdf'], 'features' => ['free_notes', 'premium_notes']],
        'articles' => ['label' => 'Articles', 'types' => ['article', 'link'], 'features' => ['articles']],
        'doubts' => ['label' => 'Doubts', 'types' => [], 'features' => ['doubts']],
        'chat' => ['label' => 'Group chat', 'types' => [], 'features' => ['chat_group']],
    ];

    public function __construct(private CourseAccessService $access) {}

    public function tabs(Course $course): array
    {
        $out = [];
        foreach (self::TABS as $key => $t) {
            if (collect($t['features'])->contains(fn ($f) => $course->feature($f))) {
                $out[] = ['key' => $key, 'label' => $t['label']];
            }
        }

        return $out;
    }

    /** Course page: info, batches to buy, demo items, teachers. */
    public function detail(Course $course, ?User $user): array
    {
        $course->load(['category:id,name,slug', 'batches' => fn ($b) => $b->where('status', 'active')->orderBy('price'), 'staff:id,name,avatar']);
        $enrolled = $this->access->isEnrolled($user, $course);
        $demo = $course->contents()->whereIn('access', ['demo', 'free'])->publishedNow()->with('contentable')->limit(12)->get();
        $counts = $course->contents()->publishedNow()->selectRaw('type, count(*) as c')->groupBy('type')->pluck('c', 'type');

        return [
            'course' => [
                'id' => $course->id,
                'slug' => $course->slug,
                'title' => $course->title,
                'category' => $course->category?->name,
                'course_type' => $course->course_type,
                'language' => $course->language,
                'thumbnail_url' => $course->thumbnail ? asset('storage/'.$course->thumbnail) : null,
                'intro_video' => $course->intro_video_url,
                'intro_youtube_id' => LiveClass::youtubeId($course->intro_video_url),
                'short_description' => $course->short_description,
                'description' => $course->description,
                'what_you_get' => $course->what_you_get,
                'rating' => $course->rating,
                'students_count' => $course->students_count,
                'features' => $course->features,
                'counts' => ['videos' => (int) ($counts['video'] ?? 0), 'live' => (int) ($counts['live'] ?? 0), 'tests' => (int) (($counts['test'] ?? 0) + ($counts['quiz'] ?? 0)), 'notes' => (int) (($counts['note'] ?? 0) + ($counts['pdf'] ?? 0)), 'articles' => (int) ($counts['article'] ?? 0)],
                'teachers' => $course->staff->where('pivot.role', 'teacher')->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar])->values(),
                'share_url' => rtrim(config('app.deep_link_host'), '/').'/c/'.$course->slug,
            ],
            'batches' => BatchResource::collection($course->batches)->resolve(),
            'demo' => $demo->map(fn ($c) => $this->item($c, $user))->values(),
            'tabs' => $this->tabs($course),
            'is_enrolled' => $enrolled,
            'is_staff' => $this->access->isStaff($user, $course),
            'my_batches' => $user ? $this->access->activeEnrollments($user, $course)->map(fn ($e) => ['batch_id' => $e->batch_id, 'expires_at' => $e->expires_at?->toIso8601String(), 'class_alerts' => $e->class_alerts])->values() : [],
        ];
    }

    /** My courses list (active + expired) with progress. */
    public function myCourses(User $user): Collection
    {
        $enrollments = Enrollment::where('user_id', $user->id)->whereIn('status', ['active', 'expired'])
            ->with(['course:id,title,slug,thumbnail,course_type', 'batch:id,name'])->latest()->get();
        $courseIds = $enrollments->pluck('course_id')->unique();
        $totals = Content::whereIn('course_id', $courseIds)->publishedNow()->selectRaw('course_id, count(*) c')->groupBy('course_id')->pluck('c', 'course_id');
        $done = ContentProgress::where('user_id', $user->id)->whereNotNull('completed_at')
            ->join('contents', 'contents.id', '=', 'content_progress.content_id')->whereIn('contents.course_id', $courseIds)
            ->selectRaw('contents.course_id, count(*) c')->groupBy('contents.course_id')->pluck('c', 'contents.course_id');

        // courses taught by a teacher appear too
        $teaching = $user->isPanelUser() ? $user->managedCourses()->get(['courses.id', 'title', 'slug', 'thumbnail', 'course_type']) : collect();

        return $enrollments->map(fn ($e) => [
            'course_id' => $e->course_id,
            'title' => $e->course?->title,
            'slug' => $e->course?->slug,
            'thumbnail_url' => $e->course?->thumbnail ? asset('storage/'.$e->course->thumbnail) : null,
            'batch' => $e->batch?->name,
            'batch_id' => $e->batch_id,
            'status' => $e->isActive() ? 'active' : 'expired',
            'expires_at' => $e->expires_at?->toIso8601String(),
            'days_left' => $e->expires_at ? max(0, (int) now()->diffInDays($e->expires_at, false)) : null,
            'progress' => ($totals[$e->course_id] ?? 0) ? (int) round(($done[$e->course_id] ?? 0) / $totals[$e->course_id] * 100) : 0,
            'role' => 'student',
            'class_alerts' => (bool) $e->class_alerts,
        ])->concat($teaching->map(fn ($c) => [
            'course_id' => $c->id, 'title' => $c->title, 'slug' => $c->slug,
            'thumbnail_url' => $c->thumbnail ? asset('storage/'.$c->thumbnail) : null,
            'batch' => null, 'batch_id' => null, 'status' => 'active', 'expires_at' => null, 'days_left' => null, 'progress' => null,
            'role' => $c->pivot->role,
        ]))->unique('course_id')->values();
    }

    /**
     * Inside a course: one tab, one folder level.
     *  folder_id null → top level: sub-folders + items outside folders
     */
    public function browse(Course $course, ?User $user, string $tab, ?int $folderId): array
    {
        if (! isset(self::TABS[$tab])) {
            throw new DomainException('Unknown tab.');
        }
        $types = self::TABS[$tab]['types'];
        $isStaff = $this->access->isStaff($user, $course);
        $batchIds = $this->access->batchIds($user, $course);

        $folder = $folderId ? CourseFolder::where('course_id', $course->id)->findOrFail($folderId) : null;
        $folders = CourseFolder::where('course_id', $course->id)->where('parent_id', $folderId)->orderBy('sort')->get()
            ->filter(fn ($f) => $isStaff || ! $f->batch_ids || array_intersect($f->batch_ids, $batchIds) || ! $batchIds)
            ->map(function ($f) use ($types, $user, $batchIds, $isStaff) {
                $count = Content::where('folder_id', $f->id)->whereIn('type', $types)->publishedNow()->count()
                    + Content::whereIn('folder_id', CourseFolder::where('parent_id', $f->id)->pluck('id'))->whereIn('type', $types)->publishedNow()->count();

                return [
                    'id' => $f->id,
                    'title' => $f->title,
                    'items' => $count,
                    'locked' => ! $isStaff && ! $this->access->folderUnlocked($f, $batchIds),
                    'unlock_at' => $f->unlock_at?->toIso8601String(),
                ];
            })->filter(fn ($f) => $f['items'] > 0)->values();

        $items = Content::where('course_id', $course->id)->where('folder_id', $folderId)->whereIn('type', $types)
            ->when(! $isStaff, fn ($q) => $q->publishedNow())
            ->with(['contentable', 'course', 'folder', 'unlockAfter:id,title'])->orderBy('sort')->get()
            ->filter(fn ($c) => $isStaff || ! $c->batch_ids || ! $batchIds || array_intersect($c->batch_ids, $batchIds) || $c->isFreeToWatch());

        $progress = $user ? ContentProgress::where('user_id', $user->id)->whereIn('content_id', $items->pluck('id'))->get()->keyBy('content_id') : collect();

        return [
            'tab' => $tab,
            'folder' => $folder ? ['id' => $folder->id, 'title' => $folder->title, 'parent_id' => $folder->parent_id] : null,
            'breadcrumbs' => $this->breadcrumbs($folder),
            'folders' => $folders,
            'items' => $items->map(fn ($c) => $this->item($c, $user, $progress[$c->id] ?? null))->values(),
        ];
    }

    /** Opens an item after checking access. Returns what the player/viewer needs. */
    public function open(Content $content, ?User $user): array
    {
        $content->loadMissing('contentable', 'course', 'folder', 'unlockAfter:id,title');
        if (! $this->access->canOpen($user, $content)) {
            throw new DomainException($this->access->lockReason($user, $content) ?? 'Locked', 403, ['locked' => true, 'course_id' => $content->course_id]);
        }
        $m = $content->contentable;
        $payload = match ($content->type) {
            'video' => VideoUrl::payload($m),
            'pdf' => ['url' => asset('storage/'.$m->file_path), 'downloadable' => $m->downloadable, 'size_bytes' => $m->size_bytes],
            'note' => ['title' => $m->title, 'body' => $m->body, 'tip' => $m->tip, 'one_liners' => $m->one_liners],
            'link' => ['url' => $m->url],
            'test', 'quiz' => ['test_id' => $m->id],
            'article' => ['article_id' => $m->id, 'slug' => $m->slug],
            'live' => $this->livePayload($m),
            default => [],
        };
        $progress = $user ? ContentProgress::where('user_id', $user->id)->where('content_id', $content->id)->first() : null;

        return $this->item($content, $user, $progress) + ['payload' => $payload];
    }

    public function saveProgress(Content $content, User $user, int $progress, int $position): ContentProgress
    {
        $p = ContentProgress::firstOrNew(['user_id' => $user->id, 'content_id' => $content->id]);
        $p->progress = max($p->progress ?? 0, min(100, $progress));
        $p->last_position = $position;
        if ($p->progress >= 90 && ! $p->completed_at) {
            $p->completed_at = now();
        }
        $p->save();

        return $p;
    }

    public function item(Content $c, ?User $user, ?ContentProgress $p = null): array
    {
        $c->loadMissing('contentable', 'course');
        $locked = ! $this->access->canOpen($user, $c);
        $m = $c->contentable;

        return [
            'id' => $c->id,
            'course_id' => $c->course_id,
            'type' => $c->type,
            'title' => $c->title,
            'description' => $c->description,
            'access' => $c->access,
            'folder_id' => $c->folder_id,
            'locked' => $locked,
            'lock_reason' => $locked ? $this->access->lockReason($user, $c) : null,
            'publish_at' => $c->publish_at?->toIso8601String(),
            'progress' => $p?->progress ?? 0,
            'completed' => (bool) $p?->completed_at,
            'last_position' => $p?->last_position ?? 0,
            'meta' => match ($c->type) {
                'video' => ['duration_sec' => $m?->duration_sec, 'thumbnail' => $m?->thumbnail],
                'pdf' => ['size_bytes' => $m?->size_bytes, 'downloadable' => $m?->downloadable],
                'test', 'quiz' => ['questions' => $m?->total_questions, 'duration_min' => $m?->duration_min, 'marks' => $m?->total_marks, 'mode' => $m?->mode],
                'live' => ['starts_at' => $m?->starts_at?->toIso8601String(), 'status' => $m?->status, 'duration_min' => $m?->duration_min],
                'article' => ['reading_min' => $m?->reading_min],
                default => [],
            },
        ];
    }

    private function livePayload(LiveClass $lc): array
    {
        return [
            'live_class_id' => $lc->id,
            'status' => $lc->status,
            'starts_at' => $lc->starts_at->toIso8601String(),
            'youtube_id' => in_array($lc->status, ['live', 'ended'], true) ? $lc->youtube_id : null,
            'stream_url' => $lc->status === 'live' ? $lc->stream_url : null,
            'recording_content_id' => $lc->recording_content_id,
        ];
    }

    private function breadcrumbs(?CourseFolder $folder): array
    {
        $out = [];
        while ($folder) {
            array_unshift($out, ['id' => $folder->id, 'title' => $folder->title]);
            $folder = $folder->parent_id ? CourseFolder::find($folder->parent_id) : null;
        }

        return $out;
    }
}
