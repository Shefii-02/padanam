<?php

namespace App\Modules\AppFeed\Services;

use App\Core\Support\Money;
use App\Models\Article;
use App\Models\Attempt;
use App\Models\ContentProgress;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\LiveClass;
use App\Models\Test;
use App\Models\User;
use App\Modules\Daily\Services\DailyQuizService;
use Illuminate\Support\Facades\DB;

/**
 * One-call screens for the Flutter app: config, setup options, home, exam list and exam hub.
 * Shapes match the app widgets (see padanam_app/lib/features/home, setup, exams).
 */
class AppFeedService
{
    public const DISTRICTS = ['Thiruvananthapuram', 'Kollam', 'Pathanamthitta', 'Alappuzha', 'Kottayam', 'Idukki', 'Ernakulam', 'Thrissur', 'Palakkad', 'Malappuram', 'Kozhikode', 'Wayanad', 'Kannur', 'Kasaragod'];

    public const TINTS = ['#E3E8FA', '#FFF1E0', '#E2F5EC', '#F1E6FB', '#FDE7EA', '#E0F2F7'];

    public function __construct(private DailyQuizService $daily) {}

    public function config(): array
    {
        return [
            'languages' => [
                ['code' => 'ml', 'label' => 'മലയാളം', 'subtitle' => 'Malayalam'],
                ['code' => 'en', 'label' => 'English', 'subtitle' => 'English'],
            ],
            'onboarding' => [
                ['emoji' => '🎓', 'title' => 'Learn in Malayalam or English', 'body' => 'Live and recorded classes from Kerala\'s top teachers.', 'color' => '#1B2A7A'],
                ['emoji' => '📝', 'title' => 'Mock tests that feel like the real exam', 'body' => 'Sectional timing, negative marks, all-Kerala rank.', 'color' => '#2F45C4'],
                ['emoji' => '📅', 'title' => 'A plan built for your exam date', 'body' => 'Daily quiz, study plan and reminders so you never fall behind.', 'color' => '#F28C1B'],
            ],
            'floats' => [],
            'support_whatsapp' => config('app.support_whatsapp'),
        ];
    }

    public function setupOptions(): array
    {
        $cats = ExamCategory::whereNull('parent_id')->where('is_active', true)->orderBy('sort')->with(['children' => fn ($c) => $c->where('is_active', true), 'exams' => fn ($e) => $e->where('is_active', true)])->get();

        return [
            'avatars' => ['🧑‍🎓', '👩‍🎓', '👨‍💼', '👩‍💼', '🧑‍💻', '👩‍🏫', '🦁', '🐯', '🦚', '🐘', '🌺', '🚀'],
            'genders' => ['male', 'female', 'other'],
            'districts' => self::DISTRICTS,
            'qualifications' => collect([
                ['sslc', 'SSLC / 10th'], ['plus_two', 'Plus Two / 12th'], ['diploma', 'Diploma / ITI'], ['degree', 'Degree'], ['pg', 'Post graduation'], ['bed', 'B.Ed / TTC'], ['other', 'Other'],
            ])->map(fn ($q) => ['value' => $q[0], 'label' => $q[1]])->all(),
            'exams' => $cats->map(fn (ExamCategory $c) => [
                'id' => $c->slug, 'name' => $c->name, 'emoji' => $c->icon ?: '📘',
                'subtitle' => $c->exams->pluck('name')->take(4)->implode(', '),
                'posts' => $c->exams->flatMap(fn (Exam $e) => array_merge([$e->full_name ?: $e->name], (array) ($e->posts ?? [])))->unique()->values()->all(),
                'age_limits' => [], 'not_eligible' => [], 'recommended' => [],
            ])->values()->all(),
            'max_exams' => 3,
            'levels' => [
                ['value' => 'beginner', 'label' => 'Just starting', 'detail' => 'I am new to this exam'],
                ['value' => 'intermediate', 'label' => 'Some preparation', 'detail' => 'I know the syllabus, need practice'],
                ['value' => 'advanced', 'label' => 'Almost ready', 'detail' => 'I need mock tests and revision'],
            ],
            'aims' => [
                ['value' => 'rank_list', 'label' => 'Get into the rank list', 'emoji' => '🎯'],
                ['value' => 'top_100', 'label' => 'Top 100 rank', 'emoji' => '🏆'],
                ['value' => 'first_attempt', 'label' => 'Clear in my first attempt', 'emoji' => '🚀'],
            ],
            'attempts' => ['First attempt', 'Second attempt', 'Third or more'],
            'slots' => [
                ['value' => 'early', 'label' => 'Early morning', 'time' => '5 – 8 AM'],
                ['value' => 'morning', 'label' => 'Morning', 'time' => '8 – 12 PM'],
                ['value' => 'afternoon', 'label' => 'Afternoon', 'time' => '12 – 5 PM'],
                ['value' => 'evening', 'label' => 'Evening', 'time' => '5 – 9 PM'],
                ['value' => 'night', 'label' => 'Night', 'time' => '9 PM – 12 AM'],
            ],
        ];
    }

    public function home(User $user): array
    {
        $user->loadMissing('interests.category', 'profile');
        $myCats = $user->interests->pluck('exam_category_id')->filter()->all();
        $cats = ExamCategory::whereNull('parent_id')->where('is_active', true)->orderBy('sort')->withCount('courses')->get();
        $categories = $cats->map(fn ($c, $i) => [
            'id' => $c->id, 'exam_id' => $c->slug, 'name' => $c->name, 'emoji' => $c->icon ?: '📘',
            'subtitle' => $c->courses_count.' courses', 'featured' => in_array($c->id, $myCats, true) || ($myCats === [] && $i < 2),
        ])->sortByDesc('featured')->values();

        $enrolledCourseIds = Enrollment::where('user_id', $user->id)->active()->pluck('course_id');
        $batchIds = Enrollment::where('user_id', $user->id)->active()->pluck('batch_id');

        // continue learning = last touched unfinished video
        $last = ContentProgress::with('content.course:id,title', 'content.contentable')->where('user_id', $user->id)->whereNull('completed_at')
            ->whereHas('content', fn ($c) => $c->where('type', 'video'))->latest('updated_at')->first();
        $continue = $last ? [
            'id' => $last->content_id, 'content_id' => $last->content_id, 'course_id' => $last->content->course_id, 'title' => $last->content->title,
            'course' => $last->content->course?->title, 'emoji' => '▶️', 'progress' => $last->progress / 100,
            'minutes_left' => max(1, (int) round((($last->content->contentable->duration_sec ?? 0) - $last->last_position) / 60)),
        ] : (object) [];

        $new = Course::published()->with(['batches' => fn ($b) => $b->where('status', 'active')->orderBy('price'), 'category:id,slug,name'])
            ->when($myCats, fn ($q) => $q->orderByRaw('FIELD(exam_category_id, '.implode(',', array_map('intval', $myCats)).') DESC'))
            ->latest('id')->limit(8)->get()->map(function (Course $c, $i) use ($enrolledCourseIds) {
                $b = $c->batches->first();

                return [
                    'id' => $c->id, 'slug' => $c->slug, 'title' => $c->title, 'exam_id' => $c->category?->slug, 'exam' => $c->category?->name,
                    'price' => (int) round(($b->price ?? 0) / 100), 'mrp' => (int) round(($b->mrp ?? 0) / 100),
                    'price_text' => $b ? Money::format($b->price) : null, 'rating' => (string) ($c->rating ?? ''),
                    'lessons' => $c->contents()->where('type', 'video')->count(), 'is_new' => $c->created_at?->gt(now()->subDays(30)) ?? false,
                    'is_enrolled' => $enrolledCourseIds->contains($c->id),
                    'thumbnail_url' => $c->thumbnail ? asset('storage/'.$c->thumbnail) : null,
                    'colors' => [['#1B2A7A', '#2F45C4'], ['#9A4C00', '#F28C1B'], ['#12704A', '#1F9D6B'], ['#5B2BB0', '#7B4BD6']][$i % 4],
                ];
            });

        $events = LiveClass::with('course:id,title')->whereIn('batch_id', $batchIds)->whereIn('status', ['scheduled', 'live'])
            ->where('starts_at', '>', now()->subHours(3))->orderBy('starts_at')->limit(6)->get()->map(fn ($l) => [
                'id' => $l->id, 'kind' => 'liveClass', 'title' => $l->title, 'subtitle' => $l->course?->title, 'start' => $l->starts_at->toIso8601String(),
                'is_live_now' => $l->status === 'live', 'viewers' => 0, 'route' => '/live/'.$l->id,
            ])->concat(Test::published()->whereNotNull('starts_at')->where('starts_at', '>', now())
                ->where(fn ($q) => $q->whereNull('course_id')->orWhereIn('course_id', $enrolledCourseIds))->orderBy('starts_at')->limit(4)->get()
                ->map(fn ($t) => ['id' => $t->id, 'kind' => 'mockTest', 'title' => $t->title, 'subtitle' => $t->total_questions.' questions · '.$t->duration_min.' min',
                    'start' => $t->starts_at->toIso8601String(), 'is_live_now' => false, 'viewers' => 0, 'route' => '/test/'.$t->id.'/instructions']))
            ->sortBy('start')->values();

        $announcements = Article::query()->where('status', 'published')->where('published_at', '<=', now())->latest('published_at')->limit(5)->get()
            ->map(fn ($a, $i) => ['id' => $a->id, 'title' => $a->title, 'body' => \Illuminate\Support\Str::limit(trim(strip_tags((string) $a->body)), 110), 'tint' => self::TINTS[$i % 6], 'emoji' => '📰',
                'cta' => 'Read', 'route' => '/article/'.$a->slug]);

        $unread = DB::table('notifications')->where('user_id', $user->id)->whereNull('read_at')->count();
        $posts = (array) ($user->profile?->target_posts ?? []);

        return [
            'greeting_name' => explode(' ', trim($user->name ?: 'Student'))[0],
            'initials' => $user->initials(),
            'target' => $posts ? 'Target: '.implode(', ', array_values($posts)) : 'Set your target exam in Profile',
            'streak_days' => $this->daily->streak($user),
            'unread_notifications' => $unread,
            'announcements' => $announcements,
            'categories' => $categories,
            'more_categories_count' => max(0, $categories->count() - 6),
            'continue_lesson' => $continue,
            'new_courses' => $new,
            'leaderboard' => $this->leaderboard($user, $cats),
            'achiever_stats' => [], 'achievers' => [],
            'events' => $events,
            'study_tools' => [
                ['label' => 'Daily quiz', 'emoji' => '⚡', 'tint' => '#FFF1E0', 'route' => '/quiz'],
                ['label' => 'Study plan', 'emoji' => '📅', 'tint' => '#E2F5EC', 'route' => '/planner'],
                ['label' => 'Previous papers', 'emoji' => '📚', 'tint' => '#E3E8FA', 'route' => '/pyq'],
                ['label' => 'Current affairs', 'emoji' => '📰', 'tint' => '#F1E6FB', 'route' => '/current-affairs'],
                ['label' => 'Ask a doubt', 'emoji' => '🙋', 'tint' => '#FDE7EA', 'route' => '/doubts'],
                ['label' => 'My courses', 'emoji' => '🎓', 'tint' => '#E0F2F7', 'route' => '/courses'],
                ['label' => 'Performance', 'emoji' => '📈', 'tint' => '#E3E8FA', 'route' => '/performance'],
                ['label' => 'Chat', 'emoji' => '💬', 'tint' => '#E2F5EC', 'route' => '/chat'],
            ],
            'promo' => $this->promo($user, $enrolledCourseIds->all()),
        ];
    }

    /** Last 30 days, average % across published tests per category (top 10 + my rank). */
    private function leaderboard(User $user, $cats): array
    {
        $entries = [];
        $mine = [];
        foreach ($cats->take(4) as $c) {
            $rows = Attempt::query()->join('tests', 'tests.id', '=', 'attempts.test_id')->join('users', 'users.id', '=', 'attempts.user_id')
                ->leftJoin('courses', 'courses.id', '=', 'tests.course_id')
                ->where('attempts.submitted_at', '>=', now()->subDays(30))->whereNotNull('attempts.score')
                ->where(fn ($q) => $q->where('courses.exam_category_id', $c->id)->orWhereNull('tests.course_id'))
                ->groupBy('attempts.user_id', 'users.name', 'users.district')
                ->selectRaw('attempts.user_id, users.name, users.district, ROUND(SUM(attempts.score)) as points')
                ->orderByDesc('points')->limit(50)->get();
            $entries[$c->slug] = $rows->take(10)->values()->map(fn ($r, $i) => ['rank' => $i + 1, 'name' => $r->name ?: 'Student', 'district' => $r->district,
                'points' => (int) $r->points, 'initials' => mb_strtoupper(mb_substr((string) $r->name, 0, 1)), 'change' => 0]);
            $pos = $rows->search(fn ($r) => $r->user_id === $user->id);
            $mine[$c->slug] = $pos === false ? (object) [] : ['rank' => $pos + 1, 'points' => (int) $rows[$pos]->points, 'change' => 0];
        }

        return ['categories' => array_keys($entries), 'entries' => $entries, 'my_ranks' => $mine];
    }

    private function promo(User $user, array $enrolled): array|object
    {
        $c = Course::published()->where('is_featured', true)->whereNotIn('id', $enrolled)->latest('id')->first();

        return $c ? ['title' => $c->title, 'subtitle' => $c->short_description ?: 'New batch – limited seats', 'cta' => 'View course', 'emoji' => '🎟️', 'route' => '/course/'.$c->slug] : (object) [];
    }

    /** Profile tab: user + small stats + menu. */
    public function profilePage(User $user): array
    {
        $user->loadMissing('profile', 'roles');
        $attempts = Attempt::where('user_id', $user->id)->whereNotNull('submitted_at')->with('test:id,total_marks')->get();
        $avg = $attempts->filter(fn ($a) => $a->test?->total_marks > 0)->avg(fn ($a) => $a->score / $a->test->total_marks * 100);
        $courses = Enrollment::where('user_id', $user->id)->active()->count();
        $menu = [
            ['icon' => '🎓', 'title' => 'My courses', 'route' => '/courses'],
            ['icon' => '🧾', 'title' => 'Orders & invoices', 'route' => '/orders'],
            ['icon' => '📈', 'title' => 'Performance', 'route' => '/performance'],
            ['icon' => '📅', 'title' => 'Study plan', 'route' => '/planner'],
            ['icon' => '🙋', 'title' => 'My doubts', 'route' => '/doubts'],
            ['icon' => '🔴', 'title' => 'Live classes', 'route' => '/live'],
            ['icon' => '🔔', 'title' => 'Notification settings', 'route' => '/notifications/settings'],
        ];
        if ($user->hasRole('teacher') || $user->isPanelUser()) {
            array_unshift($menu, ['icon' => '👩‍🏫', 'title' => 'Teacher mode', 'route' => '/teacher']);
        }
        if ($wa = config('app.support_whatsapp')) {
            $menu[] = ['icon' => '💬', 'title' => 'Help on WhatsApp', 'route' => 'https://wa.me/'.$wa];
        }

        return [
            'user' => (new \App\Modules\Auth\Resources\MeResource($user))->resolve(),
            'stats' => [
                ['label' => 'Courses', 'value' => (string) $courses],
                ['label' => 'Tests taken', 'value' => (string) $attempts->count()],
                ['label' => 'Avg score', 'value' => $avg === null ? '—' : round($avg).'%'],
                ['label' => 'Day streak', 'value' => (string) $this->daily->streak($user)],
            ],
            'menu' => $menu,
            'reminder' => (bool) ($user->profile?->reminder ?? true),
            'study_slot' => $user->profile?->study_slot,
            'referral_code' => $user->referral_code,
        ];
    }

    /** Exams tab: every category with its exams. */
    public function exams(User $user): array
    {
        $mine = $user->interests()->pluck('exam_category_id')->all();

        return ExamCategory::whereNull('parent_id')->where('is_active', true)->orderBy('sort')->withCount('courses')
            ->with(['exams' => fn ($e) => $e->where('is_active', true)])->get()
            ->map(fn ($c) => [
                'id' => $c->slug, 'name' => $c->name, 'emoji' => $c->icon ?: '📘', 'color' => $c->color, 'courses' => $c->courses_count,
                'mine' => in_array($c->id, $mine, true), 'exams' => $c->exams->map(fn ($e) => $this->examRow($e))->values(),
            ])->sortByDesc('mine')->values()->all();
    }

    /** Exam hub: one category with its exams, courses, free tests and articles. */
    public function examHub(string $slug, ?User $user): array
    {
        $c = ExamCategory::where('slug', $slug)->orWhere('id', ctype_digit($slug) ? (int) $slug : 0)->firstOrFail();
        $ids = $c->children()->pluck('id')->push($c->id);
        $courses = Course::published()->whereIn('exam_category_id', $ids)->with(['batches' => fn ($b) => $b->where('status', 'active')->orderBy('price')])->get();
        $enrolled = $user ? Enrollment::where('user_id', $user->id)->active()->pluck('course_id')->all() : [];

        return [
            'id' => $c->slug, 'name' => $c->name, 'emoji' => $c->icon ?: '📘',
            'exams' => $c->exams()->where('is_active', true)->get()->map(fn ($e) => $this->examRow($e) + ['eligibility' => $e->eligibility, 'pattern' => $e->pattern, 'posts' => $e->posts])->values(),
            'courses' => $courses->map(fn ($co) => [
                'id' => $co->id, 'slug' => $co->slug, 'title' => $co->title, 'thumbnail_url' => $co->thumbnail ? asset('storage/'.$co->thumbnail) : null,
                'price_text' => ($b = $co->batches->first()) ? Money::format($b->price) : null, 'mrp_text' => $b && $b->mrp > $b->price ? Money::format($b->mrp) : null,
                'students' => $co->students_count, 'is_enrolled' => in_array($co->id, $enrolled, true),
            ])->values(),
            'free_tests' => Test::published()->where('access', 'free')->where(fn ($q) => $q->whereIn('course_id', $courses->pluck('id'))->orWhereNull('course_id'))
                ->whereIn('kind', ['mock', 'pyq', 'sectional'])->latest('id')->limit(10)->get()
                ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'kind' => $t->kind, 'questions' => $t->total_questions, 'duration_min' => $t->duration_min])->values(),
            'articles' => Article::where('status', 'published')->whereIn('exam_category_id', $ids)->latest('published_at')->limit(6)->get(['id', 'slug', 'title', 'published_at']),
        ];
    }

    private function examRow(Exam $e): array
    {
        return ['id' => $e->id, 'slug' => $e->slug, 'name' => $e->name, 'full_name' => $e->full_name,
            'next_exam_date' => $e->next_exam_date?->toDateString(), 'days_left' => $e->next_exam_date ? max(0, (int) now()->startOfDay()->diffInDays($e->next_exam_date, false)) : null];
    }
}
