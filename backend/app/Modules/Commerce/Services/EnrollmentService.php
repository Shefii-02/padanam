<?php

namespace App\Modules\Commerce\Services;

use App\Core\Support\Audit;
use App\Core\Support\DomainException;
use App\Models\Batch;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lead;
use App\Models\Order;
use App\Models\User;
use App\Modules\Notifications\Services\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** The only place that gives or takes away course access. */
class EnrollmentService
{
    public function __construct(private Notifier $notifier) {}

    public function grant(User $user, Batch $batch, string $source, ?Order $order = null, ?Carbon $expiresAt = null, bool $ignoreSeats = false): Enrollment
    {
        $enrollment = DB::transaction(function () use ($user, $batch, $source, $order, $expiresAt, $ignoreSeats) {
            $batch = Batch::whereKey($batch->id)->lockForUpdate()->first();
            $existing = Enrollment::where('user_id', $user->id)->where('batch_id', $batch->id)->first();

            if (! $existing && ! $ignoreSeats && $batch->seat_limit !== null && $batch->seats_taken >= $batch->seat_limit) {
                throw new DomainException('Sorry, this batch is full.', 409);
            }
            $expiry = $expiresAt ?? $batch->expiryFor(now());
            if ($existing) {
                // renewal: extend from the later of now / current expiry
                $from = $existing->isActive() && $existing->expires_at ? $existing->expires_at : now();
                $existing->update([
                    'status' => 'active', 'order_id' => $order?->id ?? $existing->order_id, 'source' => $source,
                    'expires_at' => $expiresAt ?? $batch->expiryFor(Carbon::parse($from)),
                ]);
                $e = $existing;
            } else {
                $e = Enrollment::create([
                    'user_id' => $user->id, 'batch_id' => $batch->id, 'course_id' => $batch->course_id, 'order_id' => $order?->id,
                    'source' => $source, 'starts_at' => now(), 'expires_at' => $expiry, 'added_by' => auth()->id(),
                ]);
                $batch->increment('seats_taken');
                Course::whereKey($batch->course_id)->increment('students_count');
            }
            $this->joinChat($user, $batch);
            Lead::where('phone', $user->phone)->whereIn('status', ['new', 'contacted'])->update(['status' => 'converted', 'converted_at' => now(), 'user_id' => $user->id]);

            return $e;
        });

        $batch->loadMissing('course:id,title');
        $this->notifier->send([$user->id], $source === 'purchase' ? 'payment.success' : 'enrollment.manual', [
            'batch' => $batch->course->title.' – '.$batch->name, 'amount' => $order ? \App\Core\Support\Money::format($order->total) : '',
            'course_id' => $batch->course_id,
        ], ['course_id' => $batch->course_id]);

        return $enrollment;
    }

    public function revoke(Enrollment $e, string $reason = ''): void
    {
        DB::transaction(function () use ($e, $reason) {
            if ($e->status === 'active') {
                Batch::whereKey($e->batch_id)->where('seats_taken', '>', 0)->decrement('seats_taken');
            }
            $e->update(['status' => 'revoked']);
            $rooms = ChatRoom::where('batch_id', $e->batch_id)->where('type', 'batch_group')->pluck('id');
            ChatMember::whereIn('room_id', $rooms)->where('user_id', $e->user_id)->where('role', 'member')->update(['left_at' => now()]);
            Audit::log('enrollments.remove', $e, ['reason' => $reason]);
        });
    }

    public function extend(Enrollment $e, ?string $until, ?int $days): Enrollment
    {
        $new = $until ? Carbon::parse($until)->endOfDay() : Carbon::parse(max(now(), $e->expires_at ?? now()))->addDays((int) $days)->endOfDay();
        $e->update(['expires_at' => $new, 'status' => 'active']);
        Audit::log('enrollments.extend', $e, ['until' => $new->toDateString()]);

        return $e;
    }

    public function moveBatch(Enrollment $e, Batch $to): Enrollment
    {
        if ($to->course_id !== $e->course_id) {
            throw new DomainException('You can only move students between batches of the same course.');
        }

        return DB::transaction(function () use ($e, $to) {
            $user = $e->user;
            $this->revoke($e, 'moved to batch '.$to->id);
            $new = $this->grant($user, $to, $e->source, $e->order, $e->expires_at, ignoreSeats: true);
            $e->delete();

            return $new;
        });
    }

    private function joinChat(User $user, Batch $batch): void
    {
        $room = ChatRoom::where('batch_id', $batch->id)->where('type', 'batch_group')->first();
        if (! $room) {
            return;
        }
        $m = ChatMember::firstOrNew(['room_id' => $room->id, 'user_id' => $user->id]);
        $m->fill(['role' => $m->role ?? 'member', 'left_at' => null, 'joined_at' => $m->joined_at ?? now()])->save();
        $room->update(['members_count' => $room->activeMembers()->count()]);
    }
}
