<?php

namespace App\Modules\Daily\Services;

use App\Models\Enrollment;
use App\Models\StudyPlan;
use App\Models\StudyPlanTask;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Teachers make a weekly template per course/batch:
 *   week: [{day: 1..7 (Mon..Sun), items: [{type, title, minutes, content_id?}]}]
 * Each student gets real dated tasks (created on demand), can tick them off and add their own.
 */
class StudyPlanService
{
    public function saveTemplate(array $d, ?StudyPlan $plan = null): StudyPlan
    {
        $d['is_template'] = true;

        return $plan ? tap($plan)->update($d) : StudyPlan::create($d);
    }

    /** Tasks for a date range (default: this week), materialising template items for my batches. */
    public function myTasks(User $user, Carbon $from, Carbon $to): array
    {
        $enr = Enrollment::where('user_id', $user->id)->active()->get(['course_id', 'batch_id', 'starts_at']);
        $templates = StudyPlan::where('is_template', true)
            ->where(fn ($w) => $w->whereIn('batch_id', $enr->pluck('batch_id'))
                ->orWhere(fn ($x) => $x->whereNull('batch_id')->whereIn('course_id', $enr->pluck('course_id'))))->get();

        foreach ($templates as $tpl) {
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                foreach (collect($tpl->week ?? [])->where('day', $d->dayOfWeekIso)->pluck('items')->flatten(1) as $item) {
                    StudyPlanTask::firstOrCreate(
                        ['study_plan_id' => $tpl->id, 'user_id' => $user->id, 'date' => $d->toDateString(), 'title' => $item['title'] ?? 'Study'],
                        ['type' => $item['type'] ?? 'study', 'content_id' => $item['content_id'] ?? null, 'minutes' => $item['minutes'] ?? 30]
                    );
                }
            }
        }

        $tasks = StudyPlanTask::where('user_id', $user->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('content:id,type,title,course_id', 'plan:id,user_id')->orderBy('date')->orderBy('id')->get();

        return [
            'days' => $tasks->groupBy(fn ($t) => $t->date->toDateString())->map(fn ($list, $date) => [
                'date' => $date,
                'done' => $list->whereNotNull('done_at')->count(),
                'total' => $list->count(),
                'minutes' => $list->sum('minutes'),
                'tasks' => $list->map(fn ($t) => [
                    'id' => $t->id, 'title' => $t->title, 'type' => $t->type, 'minutes' => $t->minutes, 'done' => (bool) $t->done_at,
                    'content_id' => $t->content_id, 'course_id' => $t->content?->course_id, 'personal' => $t->plan?->user_id === $user->id,
                ])->values(),
            ])->values(),
            'completion' => $tasks->count() ? (int) round($tasks->whereNotNull('done_at')->count() / $tasks->count() * 100) : 0,
        ];
    }

    public function addPersonal(User $user, array $d): StudyPlanTask
    {
        $plan = StudyPlan::firstOrCreate(['user_id' => $user->id, 'is_template' => false], ['title' => 'My plan']);

        return StudyPlanTask::create(['study_plan_id' => $plan->id, 'user_id' => $user->id, 'date' => $d['date'],
            'title' => $d['title'], 'type' => $d['type'] ?? 'study', 'minutes' => $d['minutes'] ?? 30]);
    }

    public function toggle(StudyPlanTask $task, bool $done): StudyPlanTask
    {
        $task->update(['done_at' => $done ? now() : null]);

        return $task;
    }
}
