<?php

namespace App\Modules\Tests\Services;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Enrollment;
use App\Models\ExamCategory;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Screen-shaped data for the Flutter test engine:
 * test series list, instructions, result summary, analysis, solutions review, performance dashboard.
 */
class TestViewService
{
    public const COLORS = ['#2F45C4', '#F28C1B', '#1F9D6B', '#7B4BD6', '#E5263A', '#0E8FA8', '#E8A317'];

    public function __construct(private AttemptService $attempts, private ScoringService $scoring) {}

    /** status: new | done | all */
    public function series(User $user, ?string $category, string $status = 'all', ?string $kind = null): array
    {
        $myCourses = Enrollment::where('user_id', $user->id)->active()->pluck('course_id');
        $cat = $category ? ExamCategory::where('slug', $category)->first() : null;
        $tests = Test::published()->whereNotIn('kind', ['daily_quiz'])->with('sections:id,test_id')
            ->where(fn ($w) => $w->where('access', 'free')->orWhereIn('course_id', $myCourses))
            ->when($kind, fn ($w) => $w->where('kind', $kind))
            ->when($cat, fn ($w) => $w->where(fn ($x) => $x->whereNull('course_id')->orWhereHas('course', fn ($c) => $c->whereIn('exam_category_id', $cat->children()->pluck('id')->push($cat->id)))))
            ->orderByRaw('starts_at IS NULL, starts_at DESC')->latest('id')->limit(100)->get();

        $mine = Attempt::where('user_id', $user->id)->whereIn('test_id', $tests->pluck('id'))->orderBy('id')->get()->groupBy('test_id');
        $rows = $tests->map(function (Test $t) use ($mine) {
            $a = $mine->get($t->id);
            $open = $a?->firstWhere('status', 'in_progress');
            $done = $a?->whereNotNull('submitted_at')->last();
            $state = $open ? 'inc' : ($done ? 'done' : 'new');

            return [
                'id' => $t->id, 'title' => $t->title, 'kind' => $t->kind, 'status' => $state, 'attempt_id' => $open?->uid ?? $done?->uid,
                'questions' => $t->total_questions, 'marks' => (int) round($t->total_marks), 'duration_text' => $t->duration_min.' min',
                'date' => ($t->starts_at ?? $t->created_at)?->format('j M'), 'difficulty' => ucfirst($t->kind === 'pyq' ? 'PYQ' : $t->kind),
                'languages' => array_map(fn ($l) => strtoupper($l), $t->languages ?: ['en']),
                'live' => $t->starts_at && $t->starts_at->isPast() && $t->ends_at && $t->ends_at->isFuture(),
                'access' => $t->access, 'mode' => $t->mode,
            ];
        });
        if ($status === 'new') {
            $rows = $rows->whereIn('status', ['new', 'inc']);
        } elseif ($status === 'done') {
            $rows = $rows->where('status', 'done');
        }

        return [
            'exam' => [
                'title' => $cat ? $cat->name.' test series' : 'All test series', 'subtitle' => $rows->count().' tests',
                'emoji' => $cat?->icon ?: '📝', 'colors' => ['#1B2A7A', '#2F45C4'],
            ],
            'filters' => [['all', 'All'], ['new', 'Not attempted'], ['done', 'Attempted']],
            'tests' => $rows->values(),
        ];
    }

    public function instructions(Test $test, User $user): array
    {
        $test->load(['sections' => fn ($s) => $s->withCount('testQuestions')]);
        $langs = $test->languages ?: ['en'];
        $names = ['en' => 'English', 'ml' => 'മലയാളം (Malayalam)', 'hi' => 'हिन्दी (Hindi)', 'ta' => 'தமிழ் (Tamil)'];
        $neg = $test->sections->max('negative_per_question');

        return [
            'id' => $test->id, 'title' => $test->title, 'mode' => $test->mode,
            'questions' => $test->total_questions, 'marks' => (int) round($test->total_marks), 'duration_minutes' => $test->duration_min,
            'languages' => array_map(fn ($l) => ['code' => $l, 'label' => $names[$l] ?? strtoupper($l)], $langs),
            'sections' => $test->sections->map(fn ($s) => [
                'name' => $s->name, 'questions' => $s->test_questions_count, 'marks' => round($s->test_questions_count * $s->marks_per_question, 2),
                'minutes' => $test->sectional_timing ? (int) $s->duration_min : $test->duration_min,
            ])->values(),
            'sectional_timing' => (bool) $test->sectional_timing,
            'sectional_note' => $test->sectional_timing
                ? 'Each section has its own timer. When a section ends you move to the next one and cannot go back.'
                : 'One timer for the whole test. You can move between sections any time.',
            'rules' => array_values(array_filter([
                $test->instructions ? strip_tags($test->instructions) : null,
                'Every correct answer gives the marks shown for its section.',
                $neg > 0 ? "Each wrong answer takes away {$neg} marks. Unanswered questions get 0." : 'There is no negative marking.',
                'Your answers are saved automatically every few seconds. If the app closes, open the test again to continue.',
                'The test is submitted automatically when the time is over.',
                $test->attempts_allowed > 1 ? "You can attempt this test {$test->attempts_allowed} times. Only the first attempt counts for rank." : null,
            ])),
            'me' => $this->attempts->status($test, $user),
        ];
    }

    /** Result screen. */
    public function summary(Attempt $attempt): array
    {
        $r = $this->attempts->result($attempt);
        if (! ($r['published'] ?? false)) {
            return $r + ['title' => $attempt->test->title];
        }
        $test = $attempt->test->load('sections');
        $limit = $test->duration_min * 60;
        $sectionRanks = $this->sectionRanks($attempt);

        return [
            'published' => true,
            'title' => $test->title,
            'attempted_on' => $r['submitted_at'],
            'total' => [
                'score' => (float) $r['score'], 'max' => (float) $test->total_marks, 'rank' => $r['rank'] ?? 0, 'candidates' => $r['participants'],
                'percentile' => (float) ($r['percentile'] ?? 0), 'accuracy' => $r['accuracy'], 'correct' => $r['correct'], 'wrong' => $r['wrong'],
                'skipped' => $r['skipped'], 'negative' => (float) ($r['negative'] ?? 0), 'time' => $r['time_spent_sec'], 'time_max' => $limit,
                'topper' => (float) ($r['topper_score'] ?? 0), 'average' => (float) $r['average_score'],
            ],
            'sections' => collect($r['sections'])->map(fn ($s, $i) => [
                'name' => $s['name'], 'score' => (float) $s['score'], 'max' => (float) $s['max'], 'correct' => $s['correct'], 'wrong' => $s['wrong'],
                'skipped' => $s['skipped'], 'accuracy' => ($s['correct'] + $s['wrong']) ? round($s['correct'] / ($s['correct'] + $s['wrong']) * 100, 1) : 0,
                'time' => $s['time_sec'], 'time_max' => $test->sectional_timing ? (int) ($test->sections[$i]->duration_min ?? 0) * 60 : $limit,
                'rank' => $sectionRanks[$s['id']] ?? null,
            ])->values(),
            'is_reattempt' => $r['is_reattempt'],
        ];
    }

    public function analysis(Attempt $attempt, User $me): array
    {
        $test = $attempt->test->load('sections');
        $first = $this->scoring->firstAttempts($test->id)->pluck('id');
        $topper = $this->scoring->firstAttempts($test->id)->orderByDesc('score')->orderBy('time_spent')->first();

        // per-section score / accuracy / minutes for a set of attempts
        $agg = fn (Collection $ids) => DB::table('attempt_answers as a')->join('test_questions as tq', 'tq.id', '=', 'a.test_question_id')
            ->whereIn('a.attempt_id', $ids)->groupBy('tq.section_id')
            ->selectRaw('tq.section_id, SUM(a.marks) as score, SUM(a.is_correct = 1) as c, SUM(a.is_correct = 0) as w, SUM(a.time_spent) as t, COUNT(DISTINCT a.attempt_id) as n')
            ->get()->keyBy('section_id');
        $mine = $agg(collect([$attempt->id]));
        $all = $agg($first);
        $top = $topper ? $agg(collect([$topper->id])) : collect();
        $series = function ($rows, bool $avg) use ($test) {
            $out = ['score' => [], 'accuracy' => [], 'time' => []];
            foreach ($test->sections as $s) {
                $r = $rows[$s->id] ?? null;
                $n = $avg ? max(1, (int) ($r->n ?? 1)) : 1;
                $out['score'][] = round((float) ($r->score ?? 0) / $n, 2);
                $out['accuracy'][] = ($r && ($r->c + $r->w) > 0) ? round($r->c / ($r->c + $r->w) * 100, 1) : 0;
                $out['time'][] = round((float) ($r->t ?? 0) / $n / 60, 1);
            }

            return $out;
        };

        // distribution
        $answers = $attempt->answers()->with('testQuestion.question:id,difficulty')->get();
        $counts = fn ($list, $total) => ['correct' => $list->where('is_correct', true)->count(), 'wrong' => $list->where('is_correct', false)->count(),
            'skipped' => $total - $list->whereNotNull('is_correct')->count()];
        $secQ = DB::table('test_questions')->where('test_id', $test->id)->selectRaw('section_id, COUNT(*) c')->groupBy('section_id')->pluck('c', 'section_id');

        // difficulty
        $tqs = DB::table('test_questions as tq')->join('questions as q', 'q.id', '=', 'tq.question_id')->where('tq.test_id', $test->id)->pluck('q.difficulty', 'tq.id');
        $difficulty = collect(['easy' => 'Easy', 'moderate' => 'Moderate', 'hard' => 'Hard'])->map(fn ($label, $key) => [
            'level' => $label, 'total' => $tqs->filter(fn ($d) => $d === $key)->count(),
            'correct' => $answers->filter(fn ($a) => $a->is_correct && ($tqs[$a->test_question_id] ?? null) === $key)->count(),
        ])->filter(fn ($d) => $d['total'] > 0)->values();

        $lb = $this->attempts->leaderboard($test, $me, 10);
        $max = (float) $test->total_marks;

        return [
            'sections' => $test->sections->values()->map(fn ($s, $i) => ['name' => $s->name, 'short' => $s->short_name ?: mb_substr($s->name, 0, 10), 'color' => self::COLORS[$i % 7]]),
            'comparison' => [
                'metrics' => [['score', 'Marks'], ['accuracy', 'Accuracy %'], ['time', 'Time (min)']],
                'you' => $series($mine, false), 'average' => $series($all, true), 'topper' => $series($top, false),
            ],
            'marks_vs_rank' => [
                'score' => (float) $attempt->score, 'max' => $max, 'rank' => $attempt->rank ?? 0,
                'milestones' => collect([10, 50, 100])->map(function ($rank) use ($test) {
                    $s = $this->scoring->firstAttempts($test->id)->orderByDesc('score')->skip($rank - 1)->value('score');

                    return $s === null ? null : ['marks' => (int) round($s), 'label' => "for rank $rank"];
                })->filter()->values(),
            ],
            'distribution' => [
                'overall' => $counts($answers, $test->total_questions),
                'sections' => $test->sections->map(fn ($s) => $counts($answers->filter(fn ($a) => $a->testQuestion?->section_id === $s->id), (int) ($secQ[$s->id] ?? 0)))->values(),
            ],
            'difficulty' => $difficulty,
            'leaderboard' => [
                'top' => collect($lb['top'])->take(10)->map(fn ($r) => ['name' => $r['name'], 'score' => (float) $r['score'], 'rank' => $r['rank'], 'district' => $r['district']]),
                'me' => ['rank' => $lb['me']['rank'] ?? 0, 'score' => (float) ($lb['me']['score'] ?? $attempt->score), 'initials' => $me->initials()],
                'max' => $max,
            ],
        ];
    }

    /** Solutions with option indexes (the app works with indexes), avg time and % correct. */
    public function review(Attempt $attempt): array
    {
        $sections = $this->attempts->solutions($attempt);
        $test = $attempt->test;
        $first = $this->scoring->firstAttempts($test->id)->pluck('id');
        $stats = AttemptAnswer::whereIn('attempt_id', $first)->groupBy('test_question_id')
            ->selectRaw('test_question_id, AVG(time_spent) t, SUM(is_correct = 1) c, COUNT(*) n')->get()->keyBy('test_question_id');
        $diff = DB::table('test_questions as tq')->join('questions as q', 'q.id', '=', 'tq.question_id')->where('tq.test_id', $test->id)->pluck('q.difficulty', 'tq.id');
        $marked = $attempt->answers()->pluck('is_marked', 'test_question_id');
        $secMeta = $test->sections->keyBy('id');

        return ['sections' => collect($sections)->map(fn ($s) => [
            'name' => $s['name'], 'en_only' => (bool) ($secMeta[$s['id']]->en_only ?? false),
            'questions' => collect($s['questions'])->map(function ($q) use ($stats, $diff, $marked) {
                $opts = collect($q['options']);
                $correct = $opts->search(fn ($o) => $o['is_correct']);
                $sel = $q['selected'][0] ?? null;
                $mine = $sel === null ? null : $opts->search(fn ($o) => (string) $o['id'] === (string) $sel);
                $st = $stats[$q['id']] ?? null;

                return [
                    'id' => $q['id'], 'question_id' => $q['question_id'], 'no' => $q['no'], 'type' => $q['type'],
                    'q' => $q['text'], 'solution' => $q['solution'], 'options' => $opts->map(fn ($o) => $o['text'])->values(),
                    'answer' => $correct === false ? null : $correct, 'your_answer' => $mine === false ? null : $mine,
                    'numeric_answer' => $q['numeric_answer'], 'your_numeric' => $q['type'] === 'numeric' ? $sel : null,
                    'result' => $q['result'], 'marked' => (bool) ($marked[$q['id']] ?? false), 'time' => $q['time_spent'],
                    'avg_time' => (int) round($st->t ?? 0), 'percent_correct' => $st && $st->n ? (int) round($st->c / $st->n * 100) : 0,
                    'difficulty' => ucfirst($diff[$q['id']] ?? 'moderate'),
                ];
            })->values(),
        ])->values()];
    }

    /** Performance dashboard: last 10 mock / sectional / pyq attempts. */
    public function performance(User $user): array
    {
        $attempts = Attempt::where('user_id', $user->id)->whereNotNull('submitted_at')->where('submitted_at', '>=', now()->subDays(60))
            ->whereHas('test', fn ($t) => $t->whereIn('kind', ['mock', 'sectional', 'pyq', 'chapter']))
            ->with('test:id,title,total_marks,total_questions,duration_min')->orderBy('submitted_at')->get()->take(-10)->values();
        $ids = $attempts->pluck('id');
        $rows = AttemptAnswer::query()->from('attempt_answers as a')->join('test_questions as tq', 'tq.id', '=', 'a.test_question_id')
            ->join('questions as q', 'q.id', '=', 'tq.question_id')->whereIn('a.attempt_id', $ids)
            ->select('a.attempt_id', 'a.is_correct', 'a.time_spent', 'q.difficulty', 'q.subject')->get();

        $perAttempt = function ($filter) use ($attempts, $rows) {
            $out = ['attempt' => [], 'accuracy' => [], 'tpq' => []];
            foreach ($attempts as $a) {
                $r = $rows->where('attempt_id', $a->id)->filter($filter);
                $tot = max(1, $r->count() ?: ($filter ? 1 : (int) $a->test?->total_questions));
                $att = $r->whereNotNull('is_correct')->count();
                $out['attempt'][] = round($att / $tot * 100, 1);
                $out['accuracy'][] = $att ? round($r->where('is_correct', true)->count() / $att * 100, 1) : 0;
                $out['tpq'][] = $r->count() ? round($r->avg('time_spent') / 60, 2) : 0;
            }

            return $out;
        };

        $subjects = $rows->groupBy(fn ($r) => $r->subject ?: 'General')->map(function ($g, $name) {
            $att = $g->whereNotNull('is_correct')->count();
            $acc = $att ? round($g->where('is_correct', true)->count() / $att * 100, 1) : 0;

            return ['name' => $name, 'accuracy' => $acc, 'level' => $acc >= 75 ? 'Strong' : ($acc >= 50 ? 'Average' : 'Weak'),
                'cumulative_accuracy' => $acc, 'attempt_accuracy' => $g->count() ? round($att / $g->count() * 100, 1) : 0,
                'time_used' => min(100, (int) round($g->avg('time_spent') / 60 / 0.8 * 100))];
        })->sortBy('accuracy')->values()->map(fn ($s, $i) => $s + ['color' => self::COLORS[$i % 7]]);

        return [
            'labels' => $attempts->map(fn ($a) => $a->submitted_at->format('j M'))->values(),
            'marks_percent' => $attempts->map(fn ($a) => $a->test?->total_marks > 0 ? round($a->score / $a->test->total_marks * 100, 1) : 0)->values(),
            'goal_tpq' => 0.8,
            'difficulties' => [
                'Overall' => $perAttempt(null),
                'Easy' => $perAttempt(fn ($r) => $r->difficulty === 'easy'),
                'Moderate' => $perAttempt(fn ($r) => $r->difficulty === 'moderate'),
                'Hard' => $perAttempt(fn ($r) => $r->difficulty === 'hard'),
            ],
            'sections' => $subjects,
            'tests' => $attempts->map(fn ($a) => ['attempt' => $a->uid, 'title' => $a->test?->title, 'score' => $a->score, 'max' => $a->test?->total_marks, 'rank' => $a->rank])->values(),
        ];
    }

    private function sectionRanks(Attempt $attempt): array
    {
        $first = $this->scoring->firstAttempts($attempt->test_id)->pluck('id');
        $out = [];
        $scores = DB::table('attempt_answers as a')->join('test_questions as tq', 'tq.id', '=', 'a.test_question_id')
            ->whereIn('a.attempt_id', $first)->groupBy('tq.section_id', 'a.attempt_id')
            ->selectRaw('tq.section_id, a.attempt_id, SUM(a.marks) s')->get()->groupBy('section_id');
        foreach ($scores as $sid => $list) {
            $mine = $list->firstWhere('attempt_id', $attempt->id);
            if ($mine) {
                $out[$sid] = $list->where('s', '>', $mine->s)->count() + 1;
            }
        }

        return $out;
    }
}
