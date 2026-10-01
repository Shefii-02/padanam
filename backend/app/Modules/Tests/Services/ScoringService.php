<?php

namespace App\Modules\Tests\Services;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Test;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    /** Marks every answer and totals the attempt. */
    public function evaluate(Attempt $attempt): Attempt
    {
        $attempt->load(['answers.testQuestion.question.options', 'answers.testQuestion.section']);
        $score = 0.0;
        $neg = 0.0;
        $c = $w = $s = 0;

        foreach ($attempt->answers as $a) {
            $tq = $a->testQuestion;
            $q = $tq->question;
            $marks = (float) ($tq->marks ?? $tq->section?->marks_per_question ?? $q->default_marks ?? 1);
            $negative = (float) ($tq->negative ?? $tq->section?->negative_per_question ?? $q->default_negative ?? 0);
            $selected = array_values(array_filter((array) $a->selected, fn ($v) => $v !== null && $v !== ''));

            if (! $selected) {
                $a->fill(['is_correct' => null, 'marks' => 0]);
                $s++;
            } elseif ($this->isCorrect($q, $selected)) {
                $a->fill(['is_correct' => true, 'marks' => $marks]);
                $score += $marks;
                $c++;
            } else {
                $a->fill(['is_correct' => false, 'marks' => -$negative]);
                $score -= $negative;
                $neg += $negative;
                $w++;
            }
            $a->save();
        }
        // questions never opened have no answer row → skipped
        $s += max(0, $attempt->test->total_questions - $attempt->answers->count());

        $attempt->update([
            'score' => round($score, 2), 'negative' => round($neg, 2), 'correct' => $c, 'wrong' => $w, 'skipped' => $s,
            'status' => 'evaluated',
        ]);
        $this->rank($attempt);

        return $attempt->fresh();
    }

    public function isCorrect($question, array $selected): bool
    {
        if ($question->type === 'numeric') {
            $ans = trim((string) $question->numeric_answer);
            $given = trim((string) $selected[0]);

            return is_numeric($ans) && is_numeric($given) ? abs((float) $ans - (float) $given) < 0.0001 : strcasecmp($ans, $given) === 0;
        }
        $correct = $question->options->where('is_correct', true)->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all();
        $given = collect($selected)->map(fn ($v) => (int) $v)->unique()->sort()->values()->all();

        return $correct === $given;   // multi-answer: all correct and nothing extra
    }

    /** Rank among each student's FIRST attempt (re-attempts don't change the leaderboard). */
    public function rank(Attempt $attempt): void
    {
        $first = $this->firstAttempts($attempt->test_id);
        $isFirst = (clone $first)->where('attempts.id', $attempt->id)->exists();
        if (! $isFirst) {
            return;
        }
        $total = (clone $first)->count();
        $better = (clone $first)->where('score', '>', $attempt->score)->count();
        $lower = (clone $first)->where('score', '<', $attempt->score)->count();
        $attempt->update(['rank' => $better + 1, 'percentile' => $total > 1 ? round($lower / ($total - 1) * 100, 2) : 100]);
    }

    /** Re-ranks everyone (run after result publish / nightly). */
    public function rerank(Test $test): int
    {
        $rows = $this->firstAttempts($test->id)->orderByDesc('score')->orderBy('time_spent')->get(['attempts.id', 'score']);
        $total = $rows->count();
        $rank = 0;
        $prev = null;
        foreach ($rows->values() as $i => $r) {
            if ($prev === null || (float) $r->score < $prev) {
                $rank = $i + 1;
                $prev = (float) $r->score;
            }
            $lower = $rows->where('score', '<', $r->score)->count();
            Attempt::whereKey($r->id)->update(['rank' => $rank, 'percentile' => $total > 1 ? round($lower / ($total - 1) * 100, 2) : 100]);
        }

        return $total;
    }

    public function firstAttempts(int $testId)
    {
        $firstIds = DB::table('attempts')->selectRaw('MIN(id)')->where('test_id', $testId)->whereNotNull('submitted_at')->groupBy('user_id');

        return Attempt::query()->where('test_id', $testId)->whereIn('attempts.id', $firstIds);
    }
}
