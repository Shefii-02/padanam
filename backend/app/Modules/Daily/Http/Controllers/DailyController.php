<?php

namespace App\Modules\Daily\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\DailyQuiz;
use App\Models\StudyPlan;
use App\Models\StudyPlanTask;
use App\Modules\Daily\Services\DailyQuizService;
use App\Modules\Daily\Services\StudyPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DailyController extends Controller
{
    public function __construct(private DailyQuizService $quiz, private StudyPlanService $plans) {}

    // ---------- admin: daily quiz ----------
    public function calendar(Request $r)
    {
        return ApiResponse::ok($this->quiz->calendar($r->query('month', now()->format('Y-m'))));
    }

    public function plan(Request $r)
    {
        $d = $r->validate([
            'date' => 'required|date', 'exam_category_id' => 'nullable|integer|exists:exam_categories,id',
            'label_id' => 'nullable|integer|exists:labels,id', 'topic' => 'nullable|string|max:80',
            'questions' => 'nullable|integer|between:3,50', 'test_id' => 'nullable|integer|exists:tests,id',
        ]);

        return ApiResponse::ok($this->quiz->plan($d), 'Planned');
    }

    /** Plan many days at once: same label on a date range. */
    public function planRange(Request $r)
    {
        $d = $r->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'exam_category_id' => 'nullable|integer',
            'label_id' => 'required|integer|exists:labels,id', 'questions' => 'nullable|integer|between:3,50', 'skip_sundays' => 'nullable|boolean']);
        $n = 0;
        for ($day = Carbon::parse($d['from']); $day->lte(Carbon::parse($d['to'])) && $n < 120; $day->addDay()) {
            if (! empty($d['skip_sundays']) && $day->isSunday()) {
                continue;
            }
            $this->quiz->plan(['date' => $day->toDateString(), 'exam_category_id' => $d['exam_category_id'] ?? null, 'label_id' => $d['label_id'], 'questions' => $d['questions'] ?? 10]);
            $n++;
        }

        return ApiResponse::ok(['planned' => $n], "$n days planned");
    }

    public function build(DailyQuiz $dailyQuiz)
    {
        return ApiResponse::ok($this->quiz->build($dailyQuiz), 'Quiz ready – it goes live at 6 AM on that day');
    }

    public function destroy(DailyQuiz $dailyQuiz)
    {
        abort_if($dailyQuiz->status === 'published', 422, 'This quiz is already live.');
        $dailyQuiz->delete();

        return ApiResponse::ok(null, 'Removed');
    }

    // ---------- admin: study plan templates ----------
    public function templates(Request $r)
    {
        return ApiResponse::ok(StudyPlan::where('is_template', true)->with('course:id,title', 'batch:id,name')
            ->when($r->query('course_id'), fn ($w, $v) => $w->where('course_id', $v))->get());
    }

    public function saveTemplate(Request $r, ?StudyPlan $plan = null)
    {
        $d = $r->validate([
            'title' => 'required|string|max:120', 'course_id' => 'required|integer|exists:courses,id', 'batch_id' => 'nullable|integer|exists:batches,id',
            'week' => 'required|array', 'week.*.day' => 'required|integer|between:1,7', 'week.*.items' => 'array',
            'week.*.items.*.title' => 'required|string|max:120', 'week.*.items.*.type' => 'nullable|string|max:20',
            'week.*.items.*.minutes' => 'nullable|integer|between:5,600', 'week.*.items.*.content_id' => 'nullable|integer|exists:contents,id',
        ]);

        return ApiResponse::ok($this->plans->saveTemplate($d, $plan?->exists ? $plan : null), 'Study plan saved');
    }

    // ---------- app ----------
    public function today(Request $r)
    {
        return ApiResponse::ok($this->quiz->today($r->user()));
    }

    public function myPlan(Request $r)
    {
        $from = Carbon::parse($r->query('from', today()->startOfWeek()->toDateString()));
        $to = Carbon::parse($r->query('to', $from->copy()->addDays(6)->toDateString()));
        abort_if($from->diffInDays($to) > 31, 422, 'Pick up to 31 days.');

        return ApiResponse::ok($this->plans->myTasks($r->user(), $from, $to));
    }

    public function addTask(Request $r)
    {
        $d = $r->validate(['date' => 'required|date', 'title' => 'required|string|max:120', 'type' => 'nullable|string|max:20', 'minutes' => 'nullable|integer|between:5,600']);

        return ApiResponse::created($this->plans->addPersonal($r->user(), $d), 'Task added');
    }

    public function toggleTask(Request $r, StudyPlanTask $task)
    {
        abort_unless($task->user_id === $r->user()->id, 404);

        return ApiResponse::ok($this->plans->toggle($task, $r->boolean('done', true)));
    }

    public function deleteTask(Request $r, StudyPlanTask $task)
    {
        abort_unless($task->user_id === $r->user()->id && $task->plan?->user_id === $r->user()->id, 403, 'Only your own tasks can be removed.');
        $task->delete();

        return ApiResponse::ok(null, 'Removed');
    }
}
