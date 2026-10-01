<?php

namespace App\Modules\Courses\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Course;
use App\Modules\Courses\DTOs\CourseData;
use App\Modules\Courses\Http\Requests\CourseRequest;
use App\Modules\Courses\Repositories\CourseRepository;
use App\Modules\Courses\Resources\CourseResource;
use App\Modules\Courses\Services\CourseService;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function __construct(private CourseRepository $courses, private CourseService $service) {}

    public function index(Request $r)
    {
        $q = Course::query()->visibleTo($r->user())->withCount('batches');
        $page = $this->courses->paginate($r->all(), ['category:id,name', 'batches', 'staff:id,name'], $q);

        return ApiResponse::ok(CourseResource::collection($page));
    }

    /** Small list for pickers (coupons, notifications, tests). */
    public function options(Request $r)
    {
        return ApiResponse::ok(Course::query()->visibleTo($r->user())->with('batches:id,course_id,name,price,status')
            ->orderBy('title')->get(['id', 'title'])->map(fn ($c) => [
                'id' => $c->id, 'title' => $c->title,
                'batches' => $c->batches->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'status' => $b->status, 'price_text' => \App\Core\Support\Money::format((int) $b->price)]),
            ]));
    }

    public function store(CourseRequest $r)
    {
        $course = $this->service->create(CourseData::fromRequest($r), $r->user());

        return ApiResponse::created(new CourseResource($course), 'Course created with a default batch');
    }

    public function show(Course $course)
    {
        $this->authorize('view', $course);

        return ApiResponse::ok(new CourseResource($course->load(['category:id,name', 'batches.staff:id,name', 'batches.coupons:id', 'staff:id,name'])->loadCount('batches')));
    }

    public function update(CourseRequest $r, Course $course)
    {
        $this->authorize('update', $course);

        return ApiResponse::ok(new CourseResource($this->service->update($course, CourseData::fromRequest($r))), 'Changes saved');
    }

    public function publish(Course $course)
    {
        $this->authorize('publish', $course);

        return ApiResponse::ok(new CourseResource($this->service->setStatus($course, 'published')), 'Published. Students can see the course now');
    }

    public function unpublish(Course $course)
    {
        $this->authorize('publish', $course);

        return ApiResponse::ok(new CourseResource($this->service->setStatus($course, 'draft')), 'Moved to draft');
    }

    public function archive(Course $course)
    {
        $this->authorize('publish', $course);

        return ApiResponse::ok(new CourseResource($this->service->setStatus($course, 'archived')), 'Archived');
    }

    public function staff(Request $r, Course $course)
    {
        $this->authorize('update', $course);
        $d = $r->validate(['staff' => 'present|array', 'staff.*.user_id' => 'required|integer|exists:users,id', 'staff.*.role' => 'nullable|in:manager,teacher']);
        $this->service->syncStaff($course, $d['staff']);

        return ApiResponse::ok($course->staff()->get(['users.id', 'users.name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->pivot->role]), 'Access updated');
    }

    public function destroy(Course $course)
    {
        $this->authorize('delete', $course);
        $this->service->delete($course);

        return ApiResponse::ok(null, 'Course deleted');
    }

    public function share(Course $course)
    {
        $url = rtrim(config('app.deep_link_host'), '/').'/c/'.$course->slug;

        return ApiResponse::ok(['url' => $url, 'whatsapp' => 'https://wa.me/?text='.rawurlencode($course->title.' – join here: '.$url)]);
    }
}
