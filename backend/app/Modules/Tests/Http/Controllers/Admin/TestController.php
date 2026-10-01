<?php

namespace App\Modules\Tests\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Course;
use App\Models\Test;
use App\Models\TestSection;
use App\Models\User;
use App\Modules\QuestionBank\Resources\QuestionResource;
use App\Modules\Tests\Http\Requests\TestRequest;
use App\Modules\Tests\Resources\TestResource;
use App\Modules\Tests\Services\OmrService;
use App\Modules\Tests\Services\ScoringService;
use App\Modules\Tests\Services\TestBuilderService;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function __construct(private TestBuilderService $builder, private ScoringService $scoring, private OmrService $omr) {}

    public function index(Request $r)
    {
        $courseIds = Course::query()->visibleTo($r->user())->pluck('id');
        $q = Test::query()->with('course:id,title')->withCount('attempts')
            ->where(fn ($w) => $w->whereIn('course_id', $courseIds)->orWhereNull('course_id'))
            ->when($r->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($r->query('mode'), fn ($w, $v) => $w->where('mode', $v))
            ->when($r->query('kind'), fn ($w, $v) => $w->where('kind', $v))
            ->when($r->query('course_id'), fn ($w, $v) => $w->where('course_id', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->where('title', 'like', "%$v%"))
            ->latest('id');

        return ApiResponse::ok(TestResource::collection($q->paginate(min(100, $r->integer('per_page', 20)))));
    }

    public function store(TestRequest $r)
    {
        $this->courseGuard($r, $r->input('course_id'));

        return ApiResponse::created(new TestResource($this->builder->create($r->validated())), 'Test created');
    }

    public function show(Test $test)
    {
        $test->load(['course:id,title', 'sections' => fn ($s) => $s->withCount('testQuestions')])->loadCount('attempts');

        return ApiResponse::ok(new TestResource($test));
    }

    public function update(TestRequest $r, Test $test)
    {
        $this->courseGuard($r, $test->course_id);

        return ApiResponse::ok(new TestResource($this->builder->update($test, collect($r->validated())->except('sections')->all())), 'Saved');
    }

    public function destroy(Request $r, Test $test)
    {
        abort_unless($r->user()->can('tests.delete'), 403);
        abort_if($test->attempts()->whereNotNull('submitted_at')->exists(), 422, 'Students have taken this test. Archive it instead.');
        $test->delete();

        return ApiResponse::ok(null, 'Test deleted');
    }

    public function publish(Request $r, Test $test)
    {
        abort_unless($r->user()->can('tests.publish'), 403);

        return ApiResponse::ok(new TestResource($this->builder->publish($test)), 'Test published');
    }

    public function unpublish(Request $r, Test $test)
    {
        abort_unless($r->user()->can('tests.publish'), 403);
        $test->update(['status' => 'draft']);

        return ApiResponse::ok(new TestResource($test), 'Moved to draft');
    }

    public function duplicate(Request $r, Test $test)
    {
        $d = $r->validate(['title' => 'required|string|max:200']);

        return ApiResponse::created(new TestResource($this->builder->duplicate($test, $d['title'])), 'Test copied');
    }

    // ---------- sections ----------
    public function storeSection(Request $r, Test $test)
    {
        return ApiResponse::created($this->builder->saveSection($test, $this->sectionRules($r, true)), 'Section added');
    }

    public function updateSection(Request $r, TestSection $section)
    {
        return ApiResponse::ok($this->builder->saveSection($section->test, $this->sectionRules($r, false), $section), 'Section saved');
    }

    public function destroySection(TestSection $section)
    {
        $this->builder->deleteSection($section);

        return ApiResponse::ok(null, 'Section deleted');
    }

    // ---------- questions ----------
    public function questions(Test $test)
    {
        $test->load(['sections.testQuestions.question' => fn ($q) => $q->with(['translations', 'options.translations', 'labels:id,name,color'])]);

        return ApiResponse::ok($test->sections->map(fn ($s) => [
            'section' => ['id' => $s->id, 'name' => $s->name, 'marks_per_question' => $s->marks_per_question, 'negative_per_question' => $s->negative_per_question],
            'questions' => $s->testQuestions->map(fn ($tq) => [
                'id' => $tq->id, 'sort' => $tq->sort, 'marks' => $tq->marks, 'negative' => $tq->negative,
                'question' => (new QuestionResource($tq->question))->resolve(),
            ]),
        ]));
    }

    public function addQuestions(Request $r, Test $test)
    {
        $d = $r->validate(['section_id' => 'nullable|integer', 'question_ids' => 'required|array|max:500', 'question_ids.*' => 'integer|exists:questions,id']);
        $n = $this->builder->addQuestions($test, $d['section_id'] ?? null, $d['question_ids']);

        return ApiResponse::ok(['added' => $n, 'total_questions' => $test->fresh()->total_questions], "$n questions added");
    }

    public function autoPick(Request $r, Test $test)
    {
        $d = $r->validate([
            'section_id' => 'nullable|integer', 'label_ids' => 'nullable|array', 'label_ids.*' => 'integer',
            'folder_id' => 'nullable|integer', 'subject' => 'nullable|string', 'topic' => 'nullable|string', 'lang' => 'nullable|string',
            'count' => 'nullable|integer|between:1,300', 'mix' => 'nullable|array', 'mix.easy' => 'nullable|integer|min:0',
            'mix.moderate' => 'nullable|integer|min:0', 'mix.hard' => 'nullable|integer|min:0', 'exclude_used_in_tests' => 'nullable|boolean',
        ]);
        $res = $this->builder->autoPick($test, $d['section_id'] ?? null, $d);
        $msg = $res['added'].' questions added'.($res['short'] ? '. Not enough questions for: '.collect($res['short'])->map(fn ($n, $k) => "$k ($n short)")->implode(', ') : '');

        return ApiResponse::ok($res, $msg);
    }

    public function removeQuestions(Request $r, Test $test)
    {
        $d = $r->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $this->builder->removeQuestions($test, $d['ids']);

        return ApiResponse::ok(null, 'Removed');
    }

    public function arrange(Request $r, Test $test)
    {
        $d = $r->validate(['items' => 'required|array', 'items.*.id' => 'required|integer', 'items.*.sort' => 'nullable|integer',
            'items.*.section_id' => 'nullable|integer', 'items.*.marks' => 'nullable|numeric', 'items.*.negative' => 'nullable|numeric']);
        $this->builder->arrange($test, $d['items']);

        return ApiResponse::ok(null, 'Saved');
    }

    // ---------- results ----------
    public function results(Request $r, Test $test)
    {
        $q = $this->scoring->firstAttempts($test->id)->with('user:id,name,phone,district')
            ->when($r->query('search'), fn ($w, $v) => $w->whereHas('user', fn ($u) => $u->where('name', 'like', "%$v%")->orWhere('phone', 'like', "%$v%")))
            ->orderByDesc('score')->orderBy('time_spent');
        $page = $q->paginate(50);
        $first = $this->scoring->firstAttempts($test->id);

        return ApiResponse::ok(collect($page->items())->map(fn ($a) => [
            'attempt' => $a->uid, 'rank' => $a->rank, 'name' => $a->user?->name, 'phone' => $a->user?->phone, 'district' => $a->user?->district,
            'score' => $a->score, 'correct' => $a->correct, 'wrong' => $a->wrong, 'skipped' => $a->skipped,
            'time_sec' => $a->time_spent, 'percentile' => $a->percentile, 'submitted_at' => $a->submitted_at?->toIso8601String(),
        ]), 'OK', 200, [
            'pagination' => ['page' => $page->currentPage(), 'per_page' => 50, 'total' => $page->total(), 'last_page' => $page->lastPage()],
            'summary' => [
                'participants' => (clone $first)->count(),
                'average' => round((float) (clone $first)->avg('score'), 2),
                'highest' => (clone $first)->max('score'),
                'lowest' => (clone $first)->min('score'),
                'in_progress' => $test->attempts()->where('status', 'in_progress')->count(),
            ],
            'question_stats' => $this->questionStats($test),
        ]);
    }

    public function exportResults(Test $test)
    {
        $rows = $this->scoring->firstAttempts($test->id)->with('user:id,name,phone,district')->orderByDesc('score')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Rank', 'Name', 'Phone', 'District', 'Score', 'Correct', 'Wrong', 'Skipped', 'Time (min)', 'Percentile']);
            foreach ($rows as $a) {
                fputcsv($out, [$a->rank, $a->user?->name, $a->user?->phone, $a->user?->district, $a->score, $a->correct, $a->wrong, $a->skipped, round($a->time_spent / 60, 1), $a->percentile]);
            }
            fclose($out);
        }, 'results_'.$test->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function publishResult(Request $r, Test $test)
    {
        abort_unless($r->user()->can('tests.publish'), 403);
        $n = $this->scoring->rerank($test);
        $test->update(['result_published_at' => now()]);
        $users = $test->attempts()->whereNotNull('submitted_at')->distinct()->pluck('user_id')->all();
        app(\App\Modules\Notifications\Services\Notifier::class)->raw($users, 'test_result', 'Result published: '.$test->title, 'Check your score and rank.', '/test/'.$test->id.'/result', ['test_id' => $test->id]);

        return ApiResponse::ok(['ranked' => $n], 'Result published and students notified');
    }

    // ---------- OMR ----------
    public function omrSheet(Test $test)
    {
        return $this->omr->sheetPdf($test)->download('omr_'.$test->id.'.pdf');
    }

    public function answerKey(Test $test)
    {
        return ApiResponse::ok($this->omr->answerKey($test));
    }

    public function omrImport(Request $r, Test $test)
    {
        abort_unless($r->user()->can('omr.evaluate') || $r->user()->can('omr.upload'), 403);
        $r->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);
        $res = $this->omr->importCsv($test, $r->file('file'));

        return ApiResponse::ok($res, $res['evaluated'].' sheets evaluated');
    }

    public function omrEnter(Request $r, Test $test)
    {
        abort_unless($r->user()->can('omr.evaluate'), 403);
        $d = $r->validate(['user_id' => 'required|integer|exists:users,id', 'answers' => 'required|array', 'sheet_id' => 'nullable|integer|exists:omr_sheets,id']);
        $sheet = isset($d['sheet_id']) ? \App\Models\OmrSheet::where('test_id', $test->id)->findOrFail($d['sheet_id']) : null;
        $a = $this->omr->enter($test, User::findOrFail($d['user_id']), $d['answers'], $sheet);

        return ApiResponse::ok(['attempt' => $a->uid, 'score' => $a->score, 'rank' => $a->rank], 'Sheet evaluated');
    }

    public function omrPending(Test $test)
    {
        return ApiResponse::ok(\App\Models\OmrSheet::where('test_id', $test->id)->where('status', 'needs_review')->with('user:id,name,phone')->latest()->get()
            ->map(fn ($s) => ['id' => $s->id, 'user' => $s->user, 'image_url' => $s->image_path ? asset('storage/'.$s->image_path) : null, 'uploaded_at' => $s->created_at->toIso8601String()]));
    }

    private function questionStats(Test $test): array
    {
        return \Illuminate\Support\Facades\DB::table('attempt_answers')
            ->join('attempts', 'attempts.id', '=', 'attempt_answers.attempt_id')
            ->where('attempts.test_id', $test->id)->whereNotNull('attempts.submitted_at')
            ->groupBy('attempt_answers.test_question_id')
            ->selectRaw('attempt_answers.test_question_id as id, SUM(is_correct = 1) correct, SUM(is_correct = 0) wrong, AVG(time_spent) avg_time')
            ->get()->map(fn ($r) => ['id' => $r->id, 'correct' => (int) $r->correct, 'wrong' => (int) $r->wrong,
                'accuracy' => ($r->correct + $r->wrong) ? round($r->correct / ($r->correct + $r->wrong) * 100) : null, 'avg_time' => (int) $r->avg_time])->all();
    }

    private function sectionRules(Request $r, bool $c): array
    {
        return $r->validate([
            'name' => [$c ? 'required' : 'sometimes', 'string', 'max:80'], 'short_name' => 'nullable|string|max:20', 'sort' => 'nullable|integer',
            'duration_min' => 'nullable|integer|between:1,300', 'marks_per_question' => 'nullable|numeric|min:0|max:100',
            'negative_per_question' => 'nullable|numeric|min:0|max:100', 'en_only' => 'nullable|boolean',
        ]);
    }

    private function courseGuard(Request $r, $courseId): void
    {
        if ($courseId) {
            abort_unless(Course::findOrFail($courseId)->isManagedBy($r->user()), 403, 'You are not assigned to this course.');
        }
    }
}
