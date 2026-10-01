<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Core\Support\Money;
use App\Core\Support\Phone;
use App\Models\Batch;
use App\Models\CourseSwap;
use App\Models\Enrollment;
use App\Models\User;
use App\Modules\Commerce\Services\SwapService;
use Illuminate\Http\Request;

/** Admin → Admissions → Course swap. */
class SwapController extends Controller
{
    public function __construct(private SwapService $swaps) {}

    /** Find a student by phone and list their courses (active first). */
    public function lookup(Request $r)
    {
        $phone = Phone::normalize($r->query('phone'));
        abort_unless($phone, 422, 'Enter a valid 10-digit mobile number.');
        $user = User::where('phone', $phone)->first();
        if (! $user) {
            return ApiResponse::ok(null, 'No student with this number');
        }
        $enrollments = Enrollment::with('batch.course:id,title', 'order:id,order_no,total,status')->where('user_id', $user->id)
            ->orderByRaw("status = 'active' desc")->latest('id')->get()->map(fn (Enrollment $e) => [
                'id' => $e->id, 'status' => $e->status, 'source' => $e->source, 'course' => $e->batch->course->title, 'course_id' => $e->course_id,
                'batch' => $e->batch->name, 'batch_id' => $e->batch_id, 'expires_at' => $e->expires_at,
                'paid' => $e->order?->status === 'paid' ? Money::format($e->order->total) : null, 'order_no' => $e->order?->order_no,
            ]);

        return ApiResponse::ok(['user' => $user->only('id', 'name', 'phone', 'district'), 'enrollments' => $enrollments]);
    }

    public function quote(Request $r, Enrollment $enrollment)
    {
        $to = Batch::with('course')->findOrFail($r->validate(['to_batch_id' => 'required|integer|exists:batches,id'])['to_batch_id']);

        return ApiResponse::ok($this->swaps->quote($enrollment, $to));
    }

    public function store(Request $r, Enrollment $enrollment)
    {
        $d = $r->validate([
            'to_batch_id' => 'required|integer|exists:batches,id', 'mode' => 'required|in:free,collected,payment_link',
            'amount' => 'nullable|numeric|min:0|required_if:mode,collected', 'payment_mode' => 'nullable|in:cash,bank,upi_outside,cheque',
            'reference' => 'nullable|string|max:120', 'keep_expiry' => 'nullable|boolean', 'reason' => 'nullable|string|max:300',
            'send_whatsapp' => 'nullable|boolean', 'expires_in_hours' => 'nullable|integer|between:1,720',
        ]);
        $swap = $this->swaps->swap($enrollment, Batch::with('course')->findOrFail($d['to_batch_id']), $d, $r->user());

        return ApiResponse::created($this->row($swap->load('user', 'fromBatch.course', 'toBatch.course', 'order', 'creator')),
            $swap->status === 'pending_payment' ? 'Payment link created – the swap completes after payment' : 'Course swapped');
    }

    public function index(Request $r)
    {
        $q = CourseSwap::with('user:id,name,phone', 'fromBatch.course:id,title', 'toBatch.course:id,title', 'order:id,order_no,status,payment_link_url,total', 'creator:id,name')
            ->when($r->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($r->query('mode'), fn ($w, $v) => $w->where('mode', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->whereHas('user', fn ($u) => $u->where('phone', 'like', "%$v%")->orWhere('name', 'like', "%$v%")))
            ->latest('id');

        return ApiResponse::paginated($q->paginate(25)->through(fn ($s) => $this->row($s)));
    }

    public function cancel(CourseSwap $swap)
    {
        return ApiResponse::ok($this->row($this->swaps->cancel($swap)->load('user', 'fromBatch.course', 'toBatch.course', 'order', 'creator')), 'Swap cancelled');
    }

    private function row(CourseSwap $s): array
    {
        return [
            'id' => $s->id, 'student' => $s->user?->only('id', 'name', 'phone'),
            'from' => $s->fromBatch ? $s->fromBatch->course->title.' – '.$s->fromBatch->name : null,
            'to' => $s->toBatch ? $s->toBatch->course->title.' – '.$s->toBatch->name : null,
            'difference' => ($s->difference < 0 ? '−' : '').Money::format(abs($s->difference)), 'collected' => Money::format($s->collected),
            'mode' => $s->mode, 'status' => $s->status, 'keep_expiry' => $s->keep_expiry, 'reason' => $s->reason,
            'order_no' => $s->order?->order_no, 'payment_link_url' => $s->order?->payment_link_url, 'by' => $s->creator?->name,
            'created_at' => $s->created_at, 'completed_at' => $s->completed_at,
        ];
    }
}
