<?php

namespace App\Modules\Tests\Services;

use App\Core\Support\DomainException;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Content;
use App\Models\Enrollment;
use App\Models\Test;
use App\Models\User;
use App\Modules\Courses\Services\CourseAccessService;
use App\Modules\Notifications\Services\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Online test engine. The server owns the clock:
 *  - whole-test timer: started_at + duration (never past test.ends_at)
 *  - sectional timer : each section has its own time; sections move forward only
 * Expired attempts are closed automatically on the next request.
 */
class AttemptService
{
    public function __construct(private ScoringService $scoring, private CourseAccessService $access, private Notifier $notifier) {}

    // ------------------------------------------------------------ access
    public function status(Test $test, User $user): array
    {
        $done = $test->attempts()->where('user_id', $user->id)->whereNotNull('submitted_at')->count();
        $open = $test->attempts()->where('user_id', $user->id)->where('status', 'in_progress')->first();
        $reason = $this->blockReason($test, $user, $done);

        return [
            'can_start' => $reason === null || (bool) $open,
            'reason' => $open ? null : $reason,
            'attempts_used' => $done,
            'attempts_allowed' => $test->attempts_allowed,
            'in_progress_attempt' => $open?->uid,
            'last_attempt' => $test->attempts()->where('user_id', $user->id)->whereNotNull('submitted_at')->latest('id')->value('uid'),
        ];
    }

    private function blockReason(Test $test, User $user, int $done): ?string
    {
        if ($test->status !== 'published') {
            return 'This test is not available yet.';
        }
        if ($test->starts_at && $test->starts_at->isFuture()) {
            return 'Starts on '.$test->starts_at->format('j M, g:i A');
        }
        if ($test->ends_at && $test->ends_at->isPast()) {
            return 'This test has ended.';
        }
        if ($done >= $test->attempts_allowed) {
            return 'You have used all '.$test->attempts_allowed.' attempt'.($test->attempts_allowed > 1 ? 's' : '').'.';
        }
        if (! $this->hasAccess($test, $user)) {
            return 'Join the course to take this test.';
        }

        return null;
    }

    public function hasAccess(Test $test, User $user): bool
    {
        if ($test->access === 'free') {
            return true;
        }
        if ($test->course_id && ($this->access->isStaff($user, $test->course) || Enrollment::where('user_id', $user->id)->where('course_id', $test->course_id)->active()->exists())) {
            return true;
        }
        // linked into a course folder as free/demo, or inside a course the student owns
        return Content::whereIn('type', ['test', 'quiz'])->where('contentable_type', 'test')->where('contentable_id', $test->id)
            ->with('course', 'folder', 'unlockAfter')->get()->contains(fn ($c) => $this->access->canOpen($user, $c));
    }

    // ------------------------------------------------------------ attempt lifecycle
    public function start(Test $test, User $user, string $lang): Attempt
    {
        $open = $test->attempts()->where('user_id', $user->id)->where('status', 'in_progress')->first();
        if ($open) {
            return $this->checkClock($open);
        }
        $done = $test->attempts()->where('user_id', $user->id)->whereNotNull('submitted_at')->count();
        if ($reason = $this->blockReason($test, $user, $done)) {
            throw new DomainException($reason, 403);
        }
        $langs = $test->languages ?: ['en'];

        return DB::transaction(function () use ($test, $user, $lang, $langs) {
            $first = $test->sections()->orderBy('sort')->value('id');

            return Attempt::create([
                'test_id' => $test->id,
                'user_id' => $user->id,
                'language' => in_array($lang, $langs, true) ? $lang : $langs[0],
                'current_section' => 0,
                'section_time' => $test->sectional_timing && $first ? [(string) $first => ['start' => now()->timestamp]] : null,
                'started_at' => now(),
            ]);
        });
    }

    /** Everything the exam screen needs (no answers!). */
    public function paper(Attempt $attempt): array
    {
        $attempt = $this->checkClock($attempt);
        $test = $attempt->test->load(['sections.testQuestions.question.translations', 'sections.testQuestions.question.options.translations']);
        $saved = $attempt->answers()->get()->keyBy('test_question_id');
        $langs = $test->languages ?: ['en'];
        $n = 0;

        $sections = $test->sections->values()->map(function ($s, $si) use (&$n, $saved, $langs, $attempt, $test) {
            $tqs = $s->testQuestions;
            if ($test->shuffle) {
                $tqs = $tqs->sortBy(fn ($tq) => crc32($attempt->uid.$tq->id))->values();   // stable per attempt
            }

            return [
                'id' => $s->id,
                'name' => $s->name,
                'short_name' => $s->short_name ?? $s->name,
                'duration_min' => $s->duration_min,
                'marks_per_question' => $s->marks_per_question,
                'negative_per_question' => $s->negative_per_question,
                'en_only' => $s->en_only,
                'locked' => $test->sectional_timing && $si !== $attempt->current_section,
                'questions' => $tqs->map(function ($tq) use (&$n, $saved, $langs, $s) {
                    $q = $tq->question;
                    $a = $saved[$tq->id] ?? null;

                    return [
                        'id' => $tq->id,                       // test_question id – send this back
                        'question_id' => $q->id,               // for "report this question"
                        'no' => ++$n,
                        'type' => $q->type,
                        'marks' => (float) ($tq->marks ?? $s->marks_per_question ?? $q->default_marks),
                        'negative' => (float) ($tq->negative ?? $s->negative_per_question ?? $q->default_negative),
                        'text' => array_intersect_key($q->textMap(), array_flip($s->en_only ? ['en'] : $langs)) ?: $q->textMap(),
                        'options' => $q->options->map(fn ($o) => [
                            'id' => $o->id,
                            'text' => $o->translations->pluck('text', 'lang')->only($s->en_only ? ['en'] : $langs)->all() ?: $o->translations->pluck('text', 'lang')->all(),
                        ])->values(),
                        'state' => [
                            'selected' => $a?->selected ?? [],
                            'marked' => (bool) $a?->is_marked,
                            'visited' => (bool) $a?->visited,
                            'time_spent' => $a?->time_spent ?? 0,
                        ],
                    ];
                })->values(),
            ];
        });

        return [
            'attempt' => $attempt->uid,
            'status' => $attempt->status,
            'test' => [
                'id' => $test->id, 'title' => $test->title, 'instructions' => $test->instructions, 'languages' => $langs,
                'total_questions' => $test->total_questions, 'total_marks' => $test->total_marks, 'duration_min' => $test->duration_min,
                'sectional_timing' => $test->sectional_timing,
            ],
            'language' => $attempt->language,
            'current_section' => $attempt->current_section,
            'remaining_sec' => $this->remaining($attempt),
            'server_time' => now()->toIso8601String(),
            'sections' => $sections,
        ];
    }

    /**
     * answers: [{id: test_question_id, selected: [option ids] | ["12.5"], marked, visited, time_spent}]
     * Called every ~20 s and on every section change; idempotent.
     */
    public function sync(Attempt $attempt, array $answers, ?string $lang = null): array
    {
        $attempt = $this->checkClock($attempt);
        if ($attempt->status !== 'in_progress') {
            return ['status' => $attempt->status, 'remaining_sec' => 0, 'submitted' => true];
        }
        $test = $attempt->test;
        $allowed = $test->testQuestions()->when($test->sectional_timing, fn ($q) => $q->where('section_id', $this->currentSectionId($attempt)))->pluck('id')->flip();

        DB::transaction(function () use ($attempt, $answers, $allowed) {
            foreach ($answers as $a) {
                $tqId = (int) ($a['id'] ?? 0);
                if (! isset($allowed[$tqId])) {
                    continue;    // other section (sectional timing) or not in this test
                }
                AttemptAnswer::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'test_question_id' => $tqId],
                    [
                        'selected' => array_values(array_slice((array) ($a['selected'] ?? []), 0, 6)),
                        'is_marked' => (bool) ($a['marked'] ?? false),
                        'visited' => (bool) ($a['visited'] ?? true),
                        'time_spent' => min(36000, (int) ($a['time_spent'] ?? 0)),
                    ]
                );
            }
        });
        if ($lang && in_array($lang, $test->languages ?: ['en'], true)) {
            $attempt->update(['language' => $lang]);
        }

        return ['status' => 'in_progress', 'remaining_sec' => $this->remaining($attempt), 'current_section' => $attempt->current_section];
    }

    /** Sectional timing: finish this section and open the next (no going back). */
    public function nextSection(Attempt $attempt): array
    {
        $attempt = $this->checkClock($attempt);
        if (! $attempt->test->sectional_timing || $attempt->status !== 'in_progress') {
            return ['current_section' => $attempt->current_section, 'remaining_sec' => $this->remaining($attempt)];
        }
        $this->advance($attempt);

        return ['current_section' => $attempt->current_section, 'remaining_sec' => $this->remaining($attempt), 'status' => $attempt->status];
    }

    public function submit(Attempt $attempt, bool $auto = false): Attempt
    {
        if ($attempt->status !== 'in_progress') {
            return $attempt;
        }
        $attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'time_spent' => min((int) $attempt->started_at->diffInSeconds(now()), $attempt->test->duration_min * 60 + 60),
        ]);
        $attempt = $this->scoring->evaluate($attempt);

        if ($attempt->test->resultVisible() && $attempt->test->kind !== 'daily_quiz') {
            $this->notifier->send([$attempt->user_id], 'test.result', [
                'test' => $attempt->test->title, 'score' => $attempt->score.'/'.$attempt->test->total_marks,
                'rank' => $attempt->rank ?? '-', 'attempt' => $attempt->uid,
            ], ['attempt' => $attempt->uid]);
        }

        return $attempt;
    }

    // ------------------------------------------------------------ results
    public function result(Attempt $attempt): array
    {
        $test = $attempt->test;
        if ($attempt->status === 'in_progress') {
            throw new DomainException('Submit the test to see your result.');
        }
        if (! $test->resultVisible()) {
            return ['published' => false, 'message' => $test->show_result === 'after_end'
                ? 'Results will be shown after '.$test->ends_at?->format('j M, g:i A')
                : 'Your answers are saved. The result will be published soon.'];
        }
        $attempt->load('answers.testQuestion.section');
        $first = $this->scoring->firstAttempts($test->id);
        $participants = (clone $first)->count();

        $sections = $test->sections->map(function ($s) use ($attempt) {
            $ans = $attempt->answers->filter(fn ($a) => $a->testQuestion->section_id === $s->id);
            $total = $s->testQuestions()->count();

            return [
                'id' => $s->id, 'name' => $s->name,
                'score' => round($ans->sum('marks'), 2),
                'max' => round($total * $s->marks_per_question, 2),
                'correct' => $ans->where('is_correct', true)->count(),
                'wrong' => $ans->where('is_correct', false)->count(),
                'skipped' => $total - $ans->whereNotNull('is_correct')->count(),
                'time_sec' => (int) $ans->sum('time_spent'),
            ];
        })->values();

        $attempted = $attempt->correct + $attempt->wrong;

        return [
            'published' => true,
            'attempt' => $attempt->uid,
            'test' => ['id' => $test->id, 'title' => $test->title, 'total_marks' => $test->total_marks, 'total_questions' => $test->total_questions],
            'score' => $attempt->score,
            'negative' => $attempt->negative,
            'correct' => $attempt->correct,
            'wrong' => $attempt->wrong,
            'skipped' => $attempt->skipped,
            'accuracy' => $attempted ? round($attempt->correct / $attempted * 100, 1) : 0,
            'percent' => $test->total_marks > 0 ? round($attempt->score / $test->total_marks * 100, 1) : 0,
            'time_spent_sec' => $attempt->time_spent,
            'rank' => $attempt->rank,
            'percentile' => $attempt->percentile,
            'participants' => $participants,
            'topper_score' => (clone $first)->max('score'),
            'average_score' => round((float) (clone $first)->avg('score'), 2),
            'sections' => $sections,
            'is_reattempt' => $attempt->rank === null,
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
        ];
    }

    public function solutions(Attempt $attempt): array
    {
        if (! $attempt->test->resultVisible() || $attempt->status === 'in_progress') {
            throw new DomainException('Solutions open after the result is published.', 403);
        }
        $attempt->load(['answers', 'test.sections.testQuestions.question.translations', 'test.sections.testQuestions.question.options.translations']);
        $answers = $attempt->answers->keyBy('test_question_id');
        $n = 0;

        return $attempt->test->sections->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'questions' => $s->testQuestions->map(function ($tq) use (&$n, $answers) {
                $q = $tq->question;
                $a = $answers[$tq->id] ?? null;

                return [
                    'id' => $tq->id, 'no' => ++$n, 'type' => $q->type,
                    'text' => $q->textMap(), 'solution' => $q->textMap('solution'),
                    'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'text' => $o->translations->pluck('text', 'lang'), 'is_correct' => $o->is_correct])->values(),
                    'numeric_answer' => $q->type === 'numeric' ? $q->numeric_answer : null,
                    'selected' => $a?->selected ?? [],
                    'result' => $a?->is_correct === null ? 'skipped' : ($a->is_correct ? 'correct' : 'wrong'),
                    'marks' => (float) ($a?->marks ?? 0),
                    'time_spent' => $a?->time_spent ?? 0,
                    'question_id' => $q->id,     // for "report this question"
                ];
            })->values(),
        ])->values()->all();
    }

    public function leaderboard(Test $test, ?User $me, int $limit = 50): array
    {
        $rows = $this->scoring->firstAttempts($test->id)->with('user:id,name,avatar,district')
            ->orderByDesc('score')->orderBy('time_spent')->limit($limit)->get();
        $mine = $me ? $this->scoring->firstAttempts($test->id)->where('user_id', $me->id)->first() : null;

        return [
            'top' => $rows->values()->map(fn ($a, $i) => [
                'rank' => $a->rank ?? $i + 1, 'name' => $a->user?->name ?? 'Student', 'avatar' => $a->user?->avatar,
                'district' => $a->user?->district, 'score' => $a->score, 'time_sec' => $a->time_spent, 'is_me' => $me && $a->user_id === $me->id,
            ]),
            'me' => $mine ? ['rank' => $mine->rank, 'score' => $mine->score, 'percentile' => $mine->percentile] : null,
            'participants' => $this->scoring->firstAttempts($test->id)->count(),
        ];
    }

    // ------------------------------------------------------------ clock
    public function remaining(Attempt $attempt): int
    {
        $test = $attempt->test;
        if ($test->sectional_timing) {
            $sid = $this->currentSectionId($attempt);
            $sec = $test->sections->firstWhere('id', $sid);
            $start = (int) (($attempt->section_time[(string) $sid]['start'] ?? null) ?: now()->timestamp);
            $end = $start + (int) ($sec?->duration_min ?? $test->duration_min) * 60;
        } else {
            $end = $attempt->started_at->timestamp + $test->duration_min * 60;
        }
        if ($test->ends_at) {
            $end = min($end, $test->ends_at->timestamp);
        }

        return max(0, $end - now()->timestamp);
    }

    /** Applies the clock: advances sections or submits when time is up (+10 s grace for slow networks). */
    public function checkClock(Attempt $attempt): Attempt
    {
        $attempt->loadMissing('test.sections');
        while ($attempt->status === 'in_progress' && $this->remaining($attempt) <= 0 && $this->overGrace($attempt)) {
            if ($attempt->test->sectional_timing && $attempt->current_section < $attempt->test->sections->count() - 1) {
                $this->advance($attempt);
            } else {
                $attempt = $this->submit($attempt, true);
            }
        }

        return $attempt;
    }

    private function overGrace(Attempt $attempt): bool
    {
        static $grace = 10;
        $test = $attempt->test;
        $end = $test->sectional_timing
            ? (int) ($attempt->section_time[(string) $this->currentSectionId($attempt)]['start'] ?? 0) + (int) ($test->sections->firstWhere('id', $this->currentSectionId($attempt))?->duration_min ?? 0) * 60
            : $attempt->started_at->timestamp + $test->duration_min * 60;

        return now()->timestamp > $end + $grace || ($test->ends_at && $test->ends_at->isPast());
    }

    private function advance(Attempt $attempt): void
    {
        $sections = $attempt->test->sections->values();
        $times = $attempt->section_time ?? [];
        $cur = $sections[$attempt->current_section] ?? null;
        if ($cur) {
            $times[(string) $cur->id]['end'] = now()->timestamp;
        }
        if ($attempt->current_section >= $sections->count() - 1) {
            $attempt->update(['section_time' => $times]);
            $this->submit($attempt, true);

            return;
        }
        $next = $sections[$attempt->current_section + 1];
        $times[(string) $next->id] = ['start' => now()->timestamp];
        $attempt->update(['current_section' => $attempt->current_section + 1, 'section_time' => $times]);
    }

    private function currentSectionId(Attempt $attempt): ?int
    {
        return $attempt->test->sections->values()[$attempt->current_section]->id ?? null;
    }
}
