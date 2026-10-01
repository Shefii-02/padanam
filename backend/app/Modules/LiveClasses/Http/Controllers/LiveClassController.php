<?php

namespace App\Modules\LiveClasses\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Modules\LiveClasses\Resources\LiveClassResource;
use App\Modules\LiveClasses\Services\LiveClassService;
use Illuminate\Http\Request;

class LiveClassController extends Controller
{
    public function __construct(private LiveClassService $live) {}

    // ---------- admin ----------
    public function index(Request $r)
    {
        $courseIds = Course::query()->visibleTo($r->user())->pluck('id');
        $q = LiveClass::query()->whereIn('course_id', $courseIds)->with('course:id,title', 'batch:id,name', 'teacher:id,name')->withCount('attendance')
            ->when($r->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($r->query('course_id'), fn ($w, $v) => $w->where('course_id', $v))
            ->when($r->query('batch_id'), fn ($w, $v) => $w->where('batch_id', $v))
            ->when($r->query('date'), fn ($w, $v) => $w->whereDate('starts_at', $v))
            ->when($r->boolean('mine'), fn ($w) => $w->where('teacher_id', $r->user()->id))
            ->when($r->query('when') === 'upcoming', fn ($w) => $w->whereIn('status', ['scheduled', 'live'])->orderBy('starts_at'))
            ->when($r->query('when') === 'past', fn ($w) => $w->where('status', 'ended')->orderByDesc('starts_at'));

        return ApiResponse::ok(LiveClassResource::collection($q->paginate(30)));
    }

    public function store(Request $r)
    {
        $d = $this->rules($r, true);
        if ($r->filled('repeat_weekdays')) {
            $r->validate(['repeat_weekdays' => 'array', 'repeat_weekdays.*' => 'integer|between:1,7', 'repeat_until' => 'required|date|after:starts_at']);
            $list = $this->live->scheduleSeries($d, $r->user(), array_map('intval', $r->input('repeat_weekdays')), $r->input('repeat_until'));

            return ApiResponse::created(LiveClassResource::collection(collect($list)), count($list).' classes scheduled');
        }

        return ApiResponse::created(new LiveClassResource($this->live->schedule($d, $r->user())), 'Class scheduled');
    }

    public function update(Request $r, LiveClass $liveClass)
    {
        $this->guard($r, $liveClass);

        return ApiResponse::ok(new LiveClassResource($this->live->update($liveClass, $this->rules($r, false))), 'Saved');
    }

    public function goLive(Request $r, LiveClass $liveClass)
    {
        $this->guard($r, $liveClass, 'live_classes.go_live');
        $r->validate(['url' => 'required|string|max:300']);

        return ApiResponse::ok(new LiveClassResource($this->live->goLive($liveClass->load('teacher'), $r->input('url'))), 'You are live. Students were notified');
    }

    public function end(Request $r, LiveClass $liveClass)
    {
        $this->guard($r, $liveClass, 'live_classes.go_live');

        return ApiResponse::ok(new LiveClassResource($this->live->end($liveClass)), 'Class ended. Recording saved to the folder');
    }

    public function cancel(Request $r, LiveClass $liveClass)
    {
        $this->guard($r, $liveClass);

        return ApiResponse::ok(new LiveClassResource($this->live->cancel($liveClass)), 'Class cancelled');
    }

    public function attendance(Request $r, LiveClass $liveClass)
    {
        $this->guard($r, $liveClass, 'live_classes.view');
        $present = $liveClass->attendance()->with('user:id,name,phone')->get();
        $total = Enrollment::where('batch_id', $liveClass->batch_id)->active()->count();

        return ApiResponse::ok([
            'enrolled' => $total,
            'present' => $present->count(),
            'percent' => $total ? round($present->count() / $total * 100) : 0,
            'students' => $present->map(fn ($a) => ['id' => $a->user_id, 'name' => $a->user?->name, 'phone' => $a->user?->phone, 'joined_at' => $a->joined_at->toIso8601String()]),
        ]);
    }

    // ---------- app ----------
    /** Upcoming + live classes for my batches (students) or my courses (teachers). */
    public function upcoming(Request $r)
    {
        $user = $r->user();
        $batchIds = Enrollment::where('user_id', $user->id)->active()->pluck('batch_id');
        $q = LiveClass::query()->whereIn('status', ['scheduled', 'live'])->where('starts_at', '>=', now()->subHours(4))
            ->where(fn ($w) => $w->whereIn('batch_id', $batchIds)->orWhere('teacher_id', $user->id))
            ->with('course:id,title', 'batch:id,name', 'teacher:id,name')->orderBy('starts_at')->limit(30);

        return ApiResponse::ok(LiveClassResource::collection($q->get()));
    }

    public function join(Request $r, LiveClass $liveClass)
    {
        return ApiResponse::ok($this->live->join($liveClass->load('course', 'batch'), $r->user()));
    }

    private function guard(Request $r, LiveClass $lc, string $perm = 'live_classes.schedule'): void
    {
        abort_unless($r->user()->can($perm) && $lc->course->isManagedBy($r->user()), 403);
    }

    private function rules(Request $r, bool $creating): array
    {
        return $r->validate([
            'batch_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:batches,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'folder_id' => ['nullable', 'integer', 'exists:course_folders,id'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_at' => [$creating ? 'required' : 'sometimes', 'date', $creating ? 'after:now' : 'nullable'],
            'duration_min' => ['nullable', 'integer', 'between:10,600'],
            'source' => ['nullable', 'in:youtube,meet_youtube,aws'],
            'meet_url' => ['nullable', 'url'],
            'save_recording' => ['nullable', 'boolean'],
        ]);
    }
}
