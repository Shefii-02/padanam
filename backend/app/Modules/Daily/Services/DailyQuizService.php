<?php

namespace App\Modules\Daily\Services;

use App\Core\Support\DomainException;
use App\Models\Attempt;
use App\Models\DailyQuiz;
use App\Models\Question;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\TestSection;
use App\Models\User;
use App\Models\UserExamInterest;
use App\Modules\Notifications\Services\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** One short free quiz per day per exam category, auto-built from a label at 6 AM. */
class DailyQuizService
{
    public function __construct(private Notifier $notifier) {}

    public function calendar(string $month): array
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();

        return DailyQuiz::with('category:id,name', 'label:id,name', 'test:id,title,total_questions')
            ->whereBetween('date', [$start, $start->copy()->endOfMonth()])->orderBy('date')->get()
            ->map(fn ($d) => [
                'id' => $d->id, 'date' => $d->date->toDateString(), 'category' => $d->category?->name, 'category_id' => $d->exam_category_id,
                'topic' => $d->topic, 'label' => $d->label?->name, 'label_id' => $d->label_id, 'questions' => $d->questions,
                'status' => $d->status, 'test_id' => $d->test_id,
                'participants' => $d->test_id ? Attempt::where('test_id', $d->test_id)->whereNotNull('submitted_at')->distinct('user_id')->count('user_id') : 0,
            ])->all();
    }

    public function plan(array $d): DailyQuiz
    {
        return DailyQuiz::updateOrCreate(
            ['date' => $d['date'], 'exam_category_id' => $d['exam_category_id'] ?? null],
            array_intersect_key($d, array_flip(['label_id', 'topic', 'questions', 'test_id'])) + ['status' => isset($d['test_id']) ? 'ready' : 'planned']
        );
    }

    /** Builds the test for a planned day (picks questions not used in earlier daily quizzes). */
    public function build(DailyQuiz $dq): DailyQuiz
    {
        if ($dq->test_id) {
            return $dq;
        }
        if (! $dq->label_id) {
            throw new DomainException('Choose a question label for '.$dq->date->format('j M').'.');
        }

        return DB::transaction(function () use ($dq) {
            $used = TestQuestion::whereIn('test_id', Test::where('kind', 'daily_quiz')->select('id'))->pluck('question_id');
            $ids = Question::whereHas('labels', fn ($l) => $l->where('labels.id', $dq->label_id))
                ->whereNotIn('id', $used)->whereIn('type', ['mcq_single', 'true_false'])
                ->inRandomOrder()->limit($dq->questions)->pluck('id');
            if ($ids->count() < min(5, $dq->questions)) {
                throw new DomainException('Only '.$ids->count().' unused questions left in this label.');
            }
            $test = Test::create([
                'exam_category_id' => $dq->exam_category_id,
                'title' => 'Daily Quiz · '.$dq->date->format('j M Y').($dq->topic ? ' · '.$dq->topic : ''),
                'kind' => 'daily_quiz', 'access' => 'free', 'mode' => 'online', 'languages' => ['en', 'ml'],
                'duration_min' => max(5, (int) ceil($ids->count() * 0.6)), 'show_result' => 'instant', 'attempts_allowed' => 1,
                'starts_at' => $dq->date->copy()->startOfDay()->setTime(6, 0), 'ends_at' => $dq->date->copy()->endOfDay(),
                'status' => 'published',
            ]);
            $sec = TestSection::create(['test_id' => $test->id, 'name' => 'Quiz', 'marks_per_question' => 1, 'negative_per_question' => 0]);
            foreach ($ids->values() as $i => $qid) {
                TestQuestion::create(['test_id' => $test->id, 'section_id' => $sec->id, 'question_id' => $qid, 'sort' => $i + 1]);
            }
            $test->refreshTotals();
            $dq->update(['test_id' => $test->id, 'status' => 'ready']);

            return $dq->fresh();
        });
    }

    /** 6 AM job: build + publish today's quizzes and remind interested students. */
    public function publishFor(Carbon $date): int
    {
        $n = 0;
        foreach (DailyQuiz::whereDate('date', $date)->where('status', '!=', 'published')->get() as $dq) {
            try {
                $dq = $this->build($dq);
                $dq->update(['status' => 'published']);
                Test::whereKey($dq->test_id)->update(['status' => 'published']);
                $users = $dq->exam_category_id
                    ? UserExamInterest::where('exam_category_id', $dq->exam_category_id)->distinct()->pluck('user_id')->all()
                    : User::role('student')->pluck('id')->all();
                $this->notifier->raw($users, 'reminder', "Today's quiz is ready 🧠", ($dq->topic ?: 'Daily quiz').' · '.$dq->questions.' questions. Keep your streak going!', '/daily-quiz', ['test_id' => $dq->test_id]);
                $n++;
            } catch (\Throwable $e) {
                logger()->warning('Daily quiz '.$dq->id.' failed: '.$e->getMessage());
            }
        }

        return $n;
    }

    /** App: today's quizzes for my exams + streak. */
    public function today(User $user): array
    {
        $cats = $user->interests()->pluck('exam_category_id');
        $quizzes = DailyQuiz::with('test:id,title,total_questions,duration_min', 'category:id,name')
            ->whereDate('date', today())->where('status', 'published')
            ->where(fn ($w) => $w->whereNull('exam_category_id')->orWhereIn('exam_category_id', $cats))->get();
        $mine = Attempt::where('user_id', $user->id)->whereIn('test_id', $quizzes->pluck('test_id'))->get()->keyBy('test_id');

        return [
            'quizzes' => $quizzes->map(fn ($q) => [
                'id' => $q->id, 'test_id' => $q->test_id, 'title' => $q->test?->title, 'category' => $q->category?->name,
                'questions' => $q->test?->total_questions, 'duration_min' => $q->test?->duration_min,
                'done' => (bool) $mine->get($q->test_id)?->submitted_at, 'score' => $mine->get($q->test_id)?->score,
                'attempt' => $mine->get($q->test_id)?->uid,
            ])->values(),
            'streak' => $this->streak($user),
            'week' => $this->week($user),
        ];
    }

    public function streak(User $user): int
    {
        $days = Attempt::where('user_id', $user->id)->whereNotNull('submitted_at')
            ->whereHas('test', fn ($t) => $t->where('kind', 'daily_quiz'))
            ->where('submitted_at', '>=', now()->subDays(400))
            ->selectRaw('DATE(submitted_at) d')->distinct()->pluck('d')->flip();
        $streak = 0;
        $day = today();
        if (! isset($days[$day->toDateString()])) {
            $day = $day->subDay();   // today not done yet → streak still counts until midnight
        }
        while (isset($days[$day->toDateString()])) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    private function week(User $user): array
    {
        $done = Attempt::where('user_id', $user->id)->whereNotNull('submitted_at')
            ->whereHas('test', fn ($t) => $t->where('kind', 'daily_quiz'))->where('submitted_at', '>=', today()->subDays(6))
            ->selectRaw('DATE(submitted_at) d')->distinct()->pluck('d')->flip();

        return collect(range(6, 0))->map(fn ($i) => ['date' => today()->subDays($i)->toDateString(), 'done' => isset($done[today()->subDays($i)->toDateString()])])->all();
    }
}
