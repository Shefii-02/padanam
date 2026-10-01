<?php

namespace App\Modules\LiveClasses\Services;

use App\Core\Support\DomainException;
use App\Models\Batch;
use App\Models\Content;
use App\Models\Enrollment;
use App\Models\LiveClass;
use App\Models\LiveClassAttendance;
use App\Models\User;
use App\Models\Video;
use App\Modules\Notifications\Services\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Google Meet → YouTube live flow:
 *  1. schedule (creates a "live" item in the course folder)
 *  2. teacher starts Meet → Activities → Live streaming → YouTube, then pastes the YouTube link here → goLive()
 *  3. end() → the same YouTube video becomes the recording in the folder
 */
class LiveClassService
{
    public function __construct(private Notifier $notifier) {}

    public function schedule(array $d, User $by): LiveClass
    {
        $batch = Batch::with('course')->findOrFail($d['batch_id']);
        if (! $batch->course->isManagedBy($by)) {
            throw new DomainException('You are not assigned to this course.', 403);
        }
        if (isset($d['folder_id']) && ! $batch->course->folders()->whereKey($d['folder_id'])->exists()) {
            throw new DomainException('That folder belongs to another course.');
        }

        return DB::transaction(function () use ($d, $batch, $by) {
            $lc = LiveClass::create([
                'course_id' => $batch->course_id,
                'batch_id' => $batch->id,
                'teacher_id' => $d['teacher_id'] ?? ($by->isTeacher() ? $by->id : null),
                'folder_id' => $d['folder_id'] ?? null,
                'title' => $d['title'],
                'description' => $d['description'] ?? null,
                'starts_at' => $d['starts_at'],
                'duration_min' => $d['duration_min'] ?? 60,
                'source' => $d['source'] ?? 'meet_youtube',
                'meet_url' => $d['meet_url'] ?? null,
                'save_recording' => $d['save_recording'] ?? true,
            ]);
            $content = new Content([
                'course_id' => $batch->course_id, 'folder_id' => $lc->folder_id, 'type' => 'live', 'title' => $lc->title,
                'access' => 'premium', 'batch_ids' => [$batch->id], 'created_by' => $by->id,
                'sort' => (int) Content::where('course_id', $batch->course_id)->where('folder_id', $lc->folder_id)->max('sort') + 1,
            ]);
            $content->contentable()->associate($lc)->save();

            return $lc->load('batch:id,name', 'teacher:id,name');
        });
    }

    /** Weekly repeat: same time on chosen weekdays until a date. */
    public function scheduleSeries(array $d, User $by, array $weekdays, string $until): array
    {
        $out = [];
        $start = \Illuminate\Support\Carbon::parse($d['starts_at']);
        $end = \Illuminate\Support\Carbon::parse($until)->endOfDay();
        for ($day = $start->copy(), $n = 1; $day->lte($end) && count($out) < 200; $day->addDay()) {
            if (in_array($day->dayOfWeekIso, $weekdays, true)) {
                $out[] = $this->schedule(['starts_at' => $day->copy(), 'title' => $d['title'].' – '.$n++] + $d, $by);
            }
        }

        return $out;
    }

    public function update(LiveClass $lc, array $d): LiveClass
    {
        if ($lc->status === 'ended') {
            throw new DomainException('This class has ended.');
        }
        $lc->update($d);
        if (isset($d['title'])) {
            $lc->content?->update(['title' => $d['title']]);
        }
        if (isset($d['starts_at'])) {
            $lc->update(['alert_sent_at' => null]);
        }

        return $lc->fresh(['batch:id,name', 'teacher:id,name']);
    }

    public function goLive(LiveClass $lc, string $url): LiveClass
    {
        if ($lc->status === 'ended' || $lc->status === 'cancelled') {
            throw new DomainException('This class is already '.$lc->status.'.');
        }
        $id = LiveClass::youtubeId($url);
        if (($lc->source !== 'aws') && ! $id) {
            throw new DomainException('Paste the YouTube live link (youtube.com/live/… or youtu.be/…).');
        }
        $lc->update(['status' => 'live', 'stream_url' => $url, 'youtube_id' => $id, 'went_live_at' => $lc->went_live_at ?? now()]);

        $this->notifier->send($this->audience($lc, false), 'live.now', [
            'title' => $lc->title, 'teacher' => $lc->teacher?->name ?? 'Your teacher', 'id' => $lc->id,
        ], ['live_class_id' => $lc->id, 'type' => 'live_now']);
        $this->realtime('live.started', ['live_class_id' => $lc->id, 'batch_id' => $lc->batch_id, 'youtube_id' => $id]);

        return $lc;
    }

    public function end(LiveClass $lc): LiveClass
    {
        return DB::transaction(function () use ($lc) {
            $lc->update(['status' => 'ended', 'ended_at' => now()]);
            if ($lc->save_recording && $lc->youtube_id && ! $lc->recording_content_id) {
                $video = Video::create([
                    'source' => 'youtube', 'youtube_id' => $lc->youtube_id, 'url' => 'https://youtu.be/'.$lc->youtube_id,
                    'duration_sec' => $lc->went_live_at ? (int) $lc->went_live_at->diffInSeconds(now()) : $lc->duration_min * 60,
                    'thumbnail' => 'https://i.ytimg.com/vi/'.$lc->youtube_id.'/hqdefault.jpg',
                ]);
                $rec = new Content([
                    'course_id' => $lc->course_id, 'folder_id' => $lc->folder_id, 'type' => 'video',
                    'title' => $lc->title.' (Recording)', 'access' => 'premium', 'batch_ids' => [$lc->batch_id],
                    'sort' => (int) ($lc->content?->sort ?? 0) + 1,
                ]);
                $rec->contentable()->associate($video)->save();
                $lc->update(['recording_content_id' => $rec->id]);
            }
            $this->realtime('live.ended', ['live_class_id' => $lc->id, 'batch_id' => $lc->batch_id]);

            return $lc->fresh();
        });
    }

    public function cancel(LiveClass $lc): LiveClass
    {
        $lc->update(['status' => 'cancelled']);
        $lc->content?->delete();

        return $lc;
    }

    public function join(LiveClass $lc, User $user): array
    {
        $isStaff = $lc->course->isManagedBy($user) && $user->isPanelUser();
        $enrolled = Enrollment::where('user_id', $user->id)->where('batch_id', $lc->batch_id)->active()->exists();
        if (! $isStaff && ! $enrolled) {
            throw new DomainException('Join this batch to watch the live class.', 403);
        }
        if (! $isStaff) {
            LiveClassAttendance::firstOrCreate(['live_class_id' => $lc->id, 'user_id' => $user->id], ['joined_at' => now()]);
        }

        return [
            'id' => $lc->id,
            'title' => $lc->title,
            'status' => $lc->status,
            'starts_at' => $lc->starts_at->toIso8601String(),
            'youtube_id' => in_array($lc->status, ['live', 'ended'], true) ? $lc->youtube_id : null,
            'stream_url' => $lc->status === 'live' ? $lc->stream_url : null,
            'recording_content_id' => $lc->recording_content_id,
            'chat_room_id' => $lc->batch->chatRoom()->value('id'),
        ];
    }

    /** Students to alert: active enrollment in the batch, class alerts on for that course. */
    public function audience(LiveClass $lc, bool $alertsOnly = true): array
    {
        return Enrollment::where('batch_id', $lc->batch_id)->active()
            ->when($alertsOnly, fn ($q) => $q->where('class_alerts', true))
            ->pluck('user_id')->all();
    }

    /** Tell the Node realtime server (optional – fails silently). */
    private function realtime(string $event, array $payload): void
    {
        $url = config('services.realtime.url');
        $key = config('services.realtime.internal_key');
        if (! $url || ! $key) {
            return;
        }
        try {
            \Illuminate\Support\Facades\Http::timeout(2)->withHeaders(['X-Internal-Key' => $key])
                ->post(rtrim($url, '/').'/internal/emit', ['event' => $event, 'payload' => $payload]);
        } catch (\Throwable $e) {
            logger()->warning('realtime emit failed: '.$e->getMessage());
        }
    }
}
