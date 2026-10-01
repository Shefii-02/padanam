<?php

namespace App\Modules\Courses\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Batch;
use App\Models\Course;
use App\Modules\Courses\DTOs\BatchData;
use App\Modules\Courses\Http\Requests\BatchRequest;
use App\Modules\Courses\Resources\BatchResource;
use App\Modules\Courses\Services\BatchService;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    public function __construct(private BatchService $batches) {}

    public function index(Course $course)
    {
        $this->authorize('view', $course);

        return ApiResponse::ok(BatchResource::collection($course->batches()->with('staff:id,name', 'coupons:id')->withCount('enrollments')->get()));
    }

    public function store(BatchRequest $r, Course $course)
    {
        $this->authorize('manageBatches', $course);

        return ApiResponse::created(new BatchResource($this->batches->create($course, BatchData::fromRequest($r))), 'Batch added');
    }

    public function update(BatchRequest $r, Batch $batch)
    {
        $this->authorize('manageBatches', $batch->course);

        return ApiResponse::ok(new BatchResource($this->batches->update($batch, BatchData::fromRequest($r))), 'Batch saved');
    }

    public function clone(Request $r, Batch $batch)
    {
        $this->authorize('manageBatches', $batch->course);
        $d = $r->validate(['name' => 'required|string|max:80', 'starts_at' => 'nullable|date']);

        return ApiResponse::created(new BatchResource($this->batches->clone($batch, $d['name'], $d['starts_at'] ?? null)), 'Batch cloned with its schedule');
    }

    public function toggleEnrollment(Request $r, Batch $batch)
    {
        $this->authorize('manageBatches', $batch->course);
        $batch->update(['enrollment_open' => $r->boolean('open')]);

        return ApiResponse::ok(new BatchResource($batch), $batch->enrollment_open ? 'Enrollment open' : 'Enrollment closed');
    }

    public function destroy(Batch $batch)
    {
        $this->authorize('manageBatches', $batch->course);
        $this->batches->delete($batch);

        return ApiResponse::ok(null, 'Batch deleted');
    }
}
