<?php

namespace App\Modules\Courses\Services;

use App\Core\Support\Code;
use App\Core\Support\DomainException;
use App\Models\Batch;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Course;
use App\Modules\Courses\DTOs\BatchData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BatchService
{
    public function createDefault(Course $course, int $price, int $mrp): Batch
    {
        return $this->persist($course, [
            'name' => 'Default batch',
            'is_default' => true,
            'price' => $price,
            'mrp' => max($mrp, $price),
            'is_free' => $price === 0,
            'validity_type' => 'days',
            'validity_days' => 365,
        ]);
    }

    public function create(Course $course, BatchData $d): Batch
    {
        return DB::transaction(function () use ($course, $d) {
            $batch = $this->persist($course, $d->batchColumns());
            $this->syncExtras($batch, $d);

            return $batch->load('staff:id,name', 'coupons:id,code');
        });
    }

    public function update(Batch $batch, BatchData $d): Batch
    {
        return DB::transaction(function () use ($batch, $d) {
            $cols = $d->batchColumns();
            if (isset($cols['seat_limit']) && $cols['seat_limit'] !== null && $cols['seat_limit'] < $batch->seats_taken) {
                throw new DomainException("Seat limit can't be lower than the {$batch->seats_taken} students already enrolled.");
            }
            $batch->update($cols);
            $this->syncExtras($batch, $d);

            return $batch->fresh(['staff:id,name', 'coupons:id,code']);
        });
    }

    /** Copies price/validity/staff and shifts the live schedule by the date difference. */
    public function clone(Batch $source, string $name, ?string $startsAt): Batch
    {
        $source->loadMissing('staff', 'coupons', 'course');

        return DB::transaction(function () use ($source, $name, $startsAt) {
            $copy = $source->replicate(['code', 'seats_taken', 'is_default']);
            $copy->name = $name;
            $copy->code = $this->code($name);
            $copy->seats_taken = 0;
            $copy->is_default = false;
            $shiftDays = 0;
            if ($startsAt && $source->starts_at) {
                $shiftDays = (int) $source->starts_at->diffInDays(\Illuminate\Support\Carbon::parse($startsAt), false);
                $copy->starts_at = $startsAt;
                $copy->ends_at = $source->ends_at?->copy()->addDays($shiftDays);
            }
            $copy->save();
            $copy->staff()->sync($source->staff->mapWithKeys(fn ($u) => [$u->id => ['role' => $u->pivot->role]])->all());
            $copy->coupons()->sync($source->coupons->pluck('id'));

            foreach ($source->liveClasses()->where('status', 'scheduled')->get() as $lc) {
                $n = $lc->replicate(['stream_url', 'youtube_id', 'alert_sent_at', 'went_live_at', 'ended_at', 'recording_content_id']);
                $n->batch_id = $copy->id;
                $n->starts_at = $lc->starts_at->copy()->addDays($shiftDays);
                $n->status = 'scheduled';
                $n->save();
            }
            $this->ensureChatGroup($copy);

            return $copy->load('staff:id,name');
        });
    }

    public function delete(Batch $batch): void
    {
        if ($batch->enrollments()->exists()) {
            throw new DomainException('Students have joined this batch. Close enrollment or archive it instead.');
        }
        if ($batch->course->batches()->count() <= 1) {
            throw new DomainException('A course needs at least one batch.');
        }
        $batch->delete();
    }

    /** One chat group per batch (if the course uses group chat). Members are synced on enrollment. */
    public function ensureChatGroup(Batch $batch): ?ChatRoom
    {
        $batch->loadMissing('course', 'staff');
        if (! $batch->course->feature('chat_group')) {
            return null;
        }
        $room = ChatRoom::firstOrCreate(
            ['batch_id' => $batch->id, 'type' => 'batch_group'],
            [
                'name' => $batch->course->title.' – '.$batch->name,
                'avatar' => '🎓',
                'invite_code' => Code::make(8),
                'join_mode' => 'invite_only',
                'settings' => ChatRoom::DEFAULT_SETTINGS,
                'created_by' => auth()->id(),
            ]
        );
        foreach ($batch->staff as $u) {
            ChatMember::firstOrCreate(['room_id' => $room->id, 'user_id' => $u->id], ['role' => 'admin', 'joined_at' => now()]);
        }
        $room->update(['members_count' => $room->activeMembers()->count()]);

        return $room;
    }

    private function persist(Course $course, array $cols): Batch
    {
        $cols['course_id'] = $course->id;
        $cols['code'] = $this->code($cols['name'] ?? $course->title);
        $cols['sort'] ??= (int) $course->batches()->max('sort') + 1;
        $batch = Batch::create($cols);
        $batch->setRelation('course', $course);
        $this->ensureChatGroup($batch);

        return $batch;
    }

    private function syncExtras(Batch $batch, BatchData $d): void
    {
        if ($d->staff !== null) {
            $batch->staff()->sync(collect($d->staff)->mapWithKeys(fn ($s) => [(int) $s['user_id'] => ['role' => $s['role'] ?? 'teacher']])->all());
            $this->ensureChatGroup($batch->loadMissing('course'));
        }
        if ($d->coupon_ids !== null) {
            $batch->coupons()->sync($d->coupon_ids);
        }
    }

    private function code(string $name): string
    {
        $base = strtoupper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)), 8, ''));
        do {
            $code = ($base ?: 'B').Code::make(3);
        } while (Batch::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
