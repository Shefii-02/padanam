<?php

namespace App\Modules\Commerce\Services;

use App\Core\Support\Audit;
use App\Core\Support\DomainException;
use App\Core\Support\Money;
use App\Models\Batch;
use App\Models\CourseSwap;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Modules\WhatsApp\Services\CommerceMessages;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Course swap: move a student from one batch to a batch of ANY course.
 * Modes: free (no money), collected (difference received offline → manual order + invoice),
 * payment_link (difference paid online; the swap completes when the payment arrives).
 */
class SwapService
{
    public function __construct(
        private EnrollmentService $enrollments,
        private OrderService $orders,
        private WhatsAppService $wa,
    ) {}

    public function quote(Enrollment $e, Batch $to): array
    {
        $e->loadMissing('batch.course', 'order');
        $to->loadMissing('course');
        $paid = $e->order?->status === 'paid' ? (int) $e->order->total : (int) $e->batch->price;
        $diff = (int) $to->price - $paid;

        return [
            'from' => ['enrollment_id' => $e->id, 'course' => $e->batch->course->title, 'batch' => $e->batch->name, 'paid' => Money::format($paid), 'paid_paise' => $paid, 'expires_at' => $e->expires_at],
            'to' => ['batch_id' => $to->id, 'course' => $to->course->title, 'batch' => $to->name, 'price' => Money::format($to->price), 'price_paise' => (int) $to->price,
                'seats_left' => $to->seatsLeft(), 'new_expiry' => $to->expiryFor()?->toDateString()],
            'difference' => $diff, 'difference_text' => ($diff < 0 ? '−' : '').Money::format(abs($diff)),
            'suggested_mode' => $diff > 0 ? 'payment_link' : 'free',
            'same_course' => $e->course_id === $to->course_id,
        ];
    }

    public function swap(Enrollment $e, Batch $to, array $d, User $by): CourseSwap
    {
        $e->loadMissing('batch.course', 'user', 'order');
        if ($e->status !== 'active') {
            throw new DomainException('Only active enrolments can be swapped.');
        }
        if ($e->batch_id === $to->id) {
            throw new DomainException('Choose a different batch.');
        }
        if (Enrollment::where('user_id', $e->user_id)->where('batch_id', $to->id)->active()->exists()) {
            throw new DomainException('The student already has the new batch.');
        }
        if (CourseSwap::where('from_enrollment_id', $e->id)->where('status', 'pending_payment')->exists()) {
            throw new DomainException('A swap for this enrolment is already waiting for payment. Cancel it first.');
        }
        $q = $this->quote($e, $to);
        $mode = $d['mode'];
        $keep = (bool) ($d['keep_expiry'] ?? true);
        $swap = new CourseSwap([
            'user_id' => $e->user_id, 'from_enrollment_id' => $e->id, 'from_batch_id' => $e->batch_id, 'to_batch_id' => $to->id,
            'price_from' => $q['from']['paid_paise'], 'price_to' => $q['to']['price_paise'], 'difference' => $q['difference'],
            'mode' => $mode, 'keep_expiry' => $keep, 'reason' => $d['reason'] ?? null, 'created_by' => $by->id,
        ]);
        $user = $e->user;

        if ($mode === 'payment_link') {
            $amount = isset($d['amount']) ? (float) $d['amount'] : $q['difference'] / 100;
            if ($amount <= 0) {
                throw new DomainException('Nothing to collect – use “Free swap” instead.');
            }
            $order = $this->orders->paymentLink([
                'phone' => $user->phone, 'name' => $user->name, 'batch_id' => $to->id, 'amount' => $amount,
                'expires_in_hours' => $d['expires_in_hours'] ?? 72, 'note' => 'Course swap from '.$e->batch->course->title.' – '.$e->batch->name,
            ], $by);
            $swap->fill(['status' => 'pending_payment', 'order_id' => $order->id])->save();
            Audit::log('enrollments.swap_link', $swap, ['order' => $order->order_no, 'amount' => $amount]);
            if ($d['send_whatsapp'] ?? false) {
                $this->sendSafe(fn () => app(CommerceMessages::class)->paymentLink($order, 'swap_payment', ['old_course' => $e->batch->course->title]));
            }

            return $swap->load('order');
        }

        DB::transaction(function () use ($swap, $e, $to, $d, $by, $user, $keep, $mode) {
            if ($mode === 'collected') {
                $amount = (float) ($d['amount'] ?? 0);
                $order = $this->orders->manualAdmission([
                    'phone' => $user->phone, 'name' => $user->name, 'batch_id' => $to->id, 'mode' => $d['payment_mode'] ?? 'cash', 'amount' => $amount,
                    'reference' => $d['reference'] ?? null, 'note' => 'Course swap difference', 'expires_at' => $keep && $e->expires_at ? $e->expires_at->toDateString() : null,
                ], $by);
                $swap->fill(['order_id' => $order->id, 'collected' => Money::toPaise($amount)]);
            } else {
                $this->enrollments->grant($user, $to, $e->source, $e->order, $keep ? $e->expires_at : null, ignoreSeats: true);
            }
            $this->enrollments->revoke($e, 'swapped to batch '.$to->id);
            $swap->fill(['status' => 'done', 'completed_at' => now()])->save();
            Audit::log('enrollments.swap', $swap, ['from' => $e->batch_id, 'to' => $to->id, 'mode' => $mode]);
        });

        $this->notify($swap, $d['send_whatsapp'] ?? false);

        return $swap->fresh(['order']);
    }

    /** Called from OrderService::markPaid – finishes a swap that was waiting for its payment link. */
    public function completeForOrder(Order $order): void
    {
        $swap = CourseSwap::where('order_id', $order->id)->where('status', 'pending_payment')->first();
        if (! $swap) {
            return;
        }
        $old = $swap->fromEnrollment;
        if ($old && $old->status === 'active') {
            $this->enrollments->revoke($old, 'swapped to batch '.$swap->to_batch_id.' (paid '.$order->order_no.')');
        }
        if ($swap->keep_expiry && $old?->expires_at) {
            Enrollment::where('user_id', $swap->user_id)->where('batch_id', $swap->to_batch_id)->update(['expires_at' => $old->expires_at]);
        }
        $swap->update(['status' => 'done', 'completed_at' => now(), 'collected' => $order->total]);
        $this->notify($swap, (bool) (\App\Core\Support\Settings::get('whatsapp.auto')['swap'] ?? true));
    }

    public function cancel(CourseSwap $swap): CourseSwap
    {
        if ($swap->status !== 'pending_payment') {
            throw new DomainException('Only swaps waiting for payment can be cancelled.');
        }
        $swap->order?->status === 'pending' && $swap->order->update(['status' => 'cancelled']);
        $swap->update(['status' => 'cancelled']);
        Audit::log('enrollments.swap_cancel', $swap);

        return $swap;
    }

    private function notify(CourseSwap $swap, bool $whatsapp): void
    {
        if (! $whatsapp || ! $this->wa->isReady('share')) {
            return;
        }
        $swap->loadMissing('user', 'fromBatch.course', 'toBatch.course');
        $this->sendSafe(fn () => $this->wa->sendText($swap->user->phone, $this->wa->render('swap', [
            'name' => $swap->user->name ?: 'Student', 'phone' => $swap->user->phone,
            'course' => $swap->toBatch->course->title, 'batch' => $swap->toBatch->name,
            'old_course' => $swap->fromBatch->course->title, 'old_batch' => $swap->fromBatch->name,
        ]), 'swap', ['user_id' => $swap->user_id, 'order_id' => $swap->order_id]));
    }

    private function sendSafe(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::warning('Swap WhatsApp failed: '.$e->getMessage());
        }
    }
}
