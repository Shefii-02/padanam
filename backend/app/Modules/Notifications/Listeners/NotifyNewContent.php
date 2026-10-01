<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\Enrollment;
use App\Modules\Courses\Events\ContentPublished;
use App\Modules\Notifications\Services\Notifier;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyNewContent implements ShouldQueue
{
    public function __construct(private Notifier $notifier) {}

    public function handle(ContentPublished $e): void
    {
        $c = $e->content->loadMissing('course:id,title');
        if ($c->type === 'live') {
            return;   // live classes have their own alerts
        }
        $users = Enrollment::where('course_id', $c->course_id)->active()
            ->when($c->batch_ids, fn ($q) => $q->whereIn('batch_id', $c->batch_ids))
            ->pluck('user_id')->unique()->all();

        $labels = ['video' => 'New class', 'pdf' => 'New PDF', 'note' => 'New notes', 'test' => 'New test', 'quiz' => 'New quiz', 'article' => 'New article', 'link' => 'New link'];
        $this->notifier->send($users, 'content.new', [
            'course' => $c->course->title, 'type' => $labels[$c->type] ?? 'New', 'title' => $c->title, 'course_id' => $c->course_id,
        ], ['content_id' => $c->id, 'course_id' => $c->course_id]);
    }
}
