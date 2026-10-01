<?php

namespace App\Modules\Tests\Http\Controllers\App;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Attempt;
use App\Models\Enrollment;
use App\Models\QuestionReport;
use App\Models\Test;
use App\Modules\Tests\Resources\TestResource;
use App\Modules\Tests\Services\AttemptService;
use App\Modules\Tests\Services\OmrService;
use Illuminate\Http\Request;

class TestPlayController extends Controller
{
    public function __construct(private AttemptService $attempts, private OmrService $omr) {}

    /** Test series list: free tests + tests of my courses, with my status. ?kind=mock&category=kerala-psc */
    public function index(Request $r)
    {
        $user = $r->user();
        $myCourses = Enrollment::where('user_id', $user->id)->active()->pluck('course_id');
        $q = Test::published()->where('kind', '!=', 'daily_quiz')
            ->where(fn ($w) => $w->where('access', 'free')->orWhereIn('course_id', $myCourses))
            ->when($r->query('kind'), fn ($w, $v) => $w->where('kind', $v))
            ->when($r->query('course_id'), fn ($w, $v) => $w->where('course_id', $v))
            ->when($r->query('category'), fn ($w, $v) => $w->whereHas('course.category', fn ($c) => $c->where('slug', $v)))
            ->latest('id');
        $page = $q->paginate(20);
        $mine = Attempt::where('user_id', $user->id)->whereIn('test_id', $page->pluck('id'))->whereNotNull('submitted_at')
            ->orderBy('id')->get()->groupBy('test_id');
        $page->getCollection()->transform(fn ($t) => (new TestResource($t))->resolve() + [
            'my_attempts' => $mine->get($t->id)?->count() ?? 0,
            'my_best' => $mine->get($t->id)?->max('score'),
            'last_attempt' => $mine->get($t->id)?->last()?->uid,
        ]);

        return ApiResponse::ok($page->items(), 'OK', 200, ['pagination' => ['page' => $page->currentPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function show(Request $r, Test $test)
    {
        abort_unless($test->status === 'published', 404);

        return ApiResponse::ok((new TestResource($test->load('sections')))->resolve() + ['me' => $this->attempts->status($test, $r->user())]);
    }

    public function start(Request $r, Test $test)
    {
        abort_if($test->mode === 'omr', 422, 'This is an OMR test. Upload your answer sheet photo instead.');
        $attempt = $this->attempts->start($test, $r->user(), $r->input('language', $r->user()->language === 'ml' ? 'ml' : 'en'));

        return ApiResponse::ok($this->attempts->paper($attempt), 'All the best!');
    }

    public function paper(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);

        return ApiResponse::ok($this->attempts->paper($attempt));
    }

    public function sync(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);
        $r->validate(['answers' => 'present|array|max:500', 'language' => 'nullable|string|max:5']);

        return ApiResponse::ok($this->attempts->sync($attempt, $r->input('answers', []), $r->input('language')));
    }

    public function nextSection(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);
        if ($r->has('answers')) {
            $this->attempts->sync($attempt, $r->input('answers', []));
        }

        return ApiResponse::ok($this->attempts->nextSection($attempt->fresh()));
    }

    public function submit(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);
        if ($r->has('answers')) {
            $this->attempts->sync($attempt, $r->input('answers', []));
        }
        $attempt = $this->attempts->submit($attempt->fresh());

        return ApiResponse::ok($this->attempts->result($attempt), 'Submitted');
    }

    public function result(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);

        return ApiResponse::ok($this->attempts->result($this->attempts->checkClock($attempt)));
    }

    public function solutions(Request $r, Attempt $attempt)
    {
        $this->own($r, $attempt);

        return ApiResponse::ok($this->attempts->solutions($attempt));
    }

    public function leaderboard(Request $r, Test $test)
    {
        abort_unless($test->resultVisible(), 403, 'The leaderboard opens with the result.');

        return ApiResponse::ok($this->attempts->leaderboard($test, $r->user()));
    }

    public function history(Request $r)
    {
        $rows = Attempt::where('user_id', $r->user()->id)->whereNotNull('submitted_at')->with('test:id,title,total_marks,kind')
            ->latest('submitted_at')->paginate(20);

        return ApiResponse::ok(collect($rows->items())->map(fn ($a) => [
            'attempt' => $a->uid, 'test_id' => $a->test_id, 'title' => $a->test?->title, 'kind' => $a->test?->kind,
            'score' => $a->test?->resultVisible() ? $a->score : null, 'total_marks' => $a->test?->total_marks, 'rank' => $a->rank,
            'submitted_at' => $a->submitted_at->toIso8601String(),
        ]), 'OK', 200, ['pagination' => ['page' => $rows->currentPage(), 'total' => $rows->total(), 'last_page' => $rows->lastPage()]]);
    }

    public function reportQuestion(Request $r)
    {
        $d = $r->validate(['question_id' => 'required|integer|exists:questions,id', 'reason' => 'required|in:wrong_answer,wrong_question,translation,typo,image,other', 'note' => 'nullable|string|max:500']);
        QuestionReport::create($d + ['user_id' => $r->user()->id]);

        return ApiResponse::ok(null, 'Thanks! Our team will check it.');
    }

    public function uploadOmr(Request $r, Test $test)
    {
        $r->validate(['photo' => 'required|image|max:8192']);
        abort_unless($this->attempts->hasAccess($test, $r->user()), 403, 'Join the course to submit this test.');
        $this->omr->uploadPhoto($test, $r->user(), $r->file('photo'));

        return ApiResponse::ok(null, 'Sheet uploaded. Your teacher will evaluate it.');
    }

    private function own(Request $r, Attempt $attempt): void
    {
        abort_unless($attempt->user_id === $r->user()->id, 404);
    }
}
