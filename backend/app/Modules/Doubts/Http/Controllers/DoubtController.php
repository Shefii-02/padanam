<?php

namespace App\Modules\Doubts\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Course;
use App\Models\Doubt;
use App\Models\Enrollment;
use App\Modules\Notifications\Services\Notifier;
use Illuminate\Http\Request;

/** Students ask; assigned teachers answer. */
class DoubtController extends Controller
{
    public function __construct(private Notifier $notifier) {}

    public function mine(Request $r)
    {
        $q = Doubt::where('user_id', $r->user()->id)->with('course:id,title', 'answerer:id,name')->latest()->paginate(20);

        return ApiResponse::ok(collect($q->items())->map(fn ($d) => $this->row($d)), 'OK', 200, ['pagination' => ['page' => $q->currentPage(), 'total' => $q->total(), 'last_page' => $q->lastPage()]]);
    }

    /** Answered doubts from a course (students learn from each other's questions). */
    public function course(Request $r, Course $course)
    {
        $q = Doubt::where('course_id', $course->id)->whereNotNull('answered_at')->with('user:id,name,avatar', 'answerer:id,name')->latest('answered_at')->paginate(20);

        return ApiResponse::ok(collect($q->items())->map(fn ($d) => $this->row($d)));
    }

    public function ask(Request $r)
    {
        $d = $r->validate(['course_id' => 'nullable|integer|exists:courses,id', 'subject' => 'nullable|string|max:60', 'text' => 'required|string|min:5|max:2000', 'image' => 'nullable|image|max:4096']);
        if (! empty($d['course_id'])) {
            abort_unless(Enrollment::where('user_id', $r->user()->id)->where('course_id', $d['course_id'])->active()->exists(), 403, 'Join the course to ask doubts here.');
        }
        $doubt = Doubt::create([
            'user_id' => $r->user()->id, 'course_id' => $d['course_id'] ?? null, 'subject' => $d['subject'] ?? null, 'text' => $d['text'],
            'image' => $r->hasFile('image') ? $r->file('image')->store('doubts', 'public') : null,
        ]);

        return ApiResponse::created($this->row($doubt), 'Doubt sent. A teacher will reply soon');
    }

    // ---------- admin / teacher ----------
    public function inbox(Request $r)
    {
        $courseIds = Course::query()->visibleTo($r->user())->pluck('id');
        $q = Doubt::with('user:id,name,phone', 'course:id,title', 'answerer:id,name')
            ->where(fn ($w) => $w->whereIn('course_id', $courseIds)->orWhere(fn ($x) => $x->whereNull('course_id')->when(! $r->user()->isAdmin(), fn ($y) => $y->whereRaw('1=0'))))
            ->when($r->query('status') === 'open', fn ($w) => $w->whereNull('answered_at'))
            ->when($r->query('status') === 'answered', fn ($w) => $w->whereNotNull('answered_at'))
            ->when($r->query('course_id'), fn ($w, $v) => $w->where('course_id', $v))
            ->oldest()->paginate(30);

        return ApiResponse::ok(collect($q->items())->map(fn ($d) => $this->row($d) + ['student' => $d->user?->only(['id', 'name', 'phone'])]), 'OK', 200,
            ['pagination' => ['page' => $q->currentPage(), 'total' => $q->total(), 'last_page' => $q->lastPage()]]);
    }

    public function answer(Request $r, Doubt $doubt)
    {
        abort_if($doubt->course_id && ! $doubt->course->isManagedBy($r->user()), 403);
        $d = $r->validate(['answer' => 'required|string|min:2|max:5000']);
        $doubt->update(['answer' => $d['answer'], 'answered_by' => $r->user()->id, 'answered_at' => now()]);
        $this->notifier->raw([$doubt->user_id], 'course_update', 'Your doubt was answered ✅', mb_strimwidth($d['answer'], 0, 90, '…'), '/doubts/'.$doubt->id, ['doubt_id' => $doubt->id]);

        return ApiResponse::ok($this->row($doubt->load('answerer:id,name')), 'Answer sent');
    }

    private function row(Doubt $d): array
    {
        return [
            'id' => $d->id, 'course' => $d->course?->title, 'course_id' => $d->course_id, 'subject' => $d->subject, 'text' => $d->text,
            'image_url' => $d->image ? asset('storage/'.$d->image) : null, 'answer' => $d->answer, 'answered_by' => $d->answerer?->name,
            'answered_at' => $d->answered_at?->toIso8601String(), 'asked_by' => $d->relationLoaded('user') ? $d->user?->name : null,
            'created_at' => $d->created_at->toIso8601String(),
        ];
    }
}
