<?php

namespace App\Modules\Commerce\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Modules\Commerce\Services\EnrollmentService;
use Illuminate\Http\Request;

/** Students tab of a batch: list, remove, extend, move. */
class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $enrollments) {}

    public function index(Request $r, Batch $batch)
    {
        abort_unless($batch->course->isManagedBy($r->user()), 403);
        $q = Enrollment::where('batch_id', $batch->id)->with('user:id,name,phone,district,last_seen_at', 'order:id,order_no,total,gateway')
            ->when($r->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($r->query('source'), fn ($w, $v) => $w->where('source', $v))
            ->when($r->query('search'), fn ($w, $v) => $w->whereHas('user', fn ($u) => $u->where('name', 'like', "%$v%")->orWhere('phone', 'like', "%$v%")))
            ->latest()->paginate(50);

        return ApiResponse::ok(collect($q->items())->map(fn ($e) => [
            'id' => $e->id, 'user' => $e->user?->only(['id', 'name', 'phone', 'district']), 'source' => $e->source, 'status' => $e->isActive() ? 'active' : $e->status,
            'starts_at' => $e->starts_at?->toDateString(), 'expires_at' => $e->expires_at?->toDateString(), 'class_alerts' => $e->class_alerts,
            'order_no' => $e->order?->order_no, 'paid' => $e->order ? \App\Core\Support\Money::format($e->order->total) : null,
            'last_seen_at' => $e->user?->last_seen_at?->toIso8601String(),
        ]), 'OK', 200, ['pagination' => ['page' => $q->currentPage(), 'total' => $q->total(), 'last_page' => $q->lastPage()]]);
    }

    public function remove(Request $r, Enrollment $enrollment)
    {
        abort_unless($r->user()->can('enrollments.remove'), 403);
        $this->enrollments->revoke($enrollment, (string) $r->input('reason'));

        return ApiResponse::ok(null, 'Access removed');
    }

    public function extend(Request $r, Enrollment $enrollment)
    {
        abort_unless($r->user()->can('enrollments.extend'), 403);
        $d = $r->validate(['until' => 'nullable|date|after:today', 'days' => 'nullable|integer|between:1,3650|required_without:until']);

        return ApiResponse::ok($this->enrollments->extend($enrollment, $d['until'] ?? null, $d['days'] ?? null), 'Access extended');
    }

    public function move(Request $r, Enrollment $enrollment)
    {
        abort_unless($r->user()->can('enrollments.add_manual'), 403);
        $d = $r->validate(['batch_id' => 'required|integer|exists:batches,id']);

        return ApiResponse::ok($this->enrollments->moveBatch($enrollment->load('user', 'order'), Batch::findOrFail($d['batch_id'])), 'Moved to the new batch');
    }
}
