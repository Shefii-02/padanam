<?php

namespace App\Modules\Courses\Http\Controllers\App;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Modules\Courses\Repositories\CourseRepository;
use App\Modules\Courses\Resources\CourseCardResource;
use App\Modules\Courses\Services\StudentCourseService;
use Illuminate\Http\Request;

/** Course store (works without login too – guests see prices and demos). */
class StoreController extends Controller
{
    public function __construct(private CourseRepository $courses, private StudentCourseService $learning) {}

    public function index(Request $r)
    {
        $page = $this->courses->store($r->only(['category', 'search', 'pricing']))->paginate(20);
        $user = auth('api')->user();
        if ($user) {
            $mine = Enrollment::where('user_id', $user->id)->active()->pluck('course_id')->all();
            $page->getCollection()->each(fn ($c) => $c->is_enrolled = in_array($c->id, $mine, true));
        }

        return ApiResponse::ok(CourseCardResource::collection($page));
    }

    public function show(string $slug)
    {
        $course = Course::published()
            ->where(fn ($q) => $q->where('slug', $slug)->orWhere('id', ctype_digit($slug) ? (int) $slug : 0))
            ->firstOrFail();
        if ($user = auth('api')->user()) {
            app(\App\Modules\Leads\Services\LeadService::class)->track($user, 'course_view', ['course_id' => $course->id, 'category_id' => $course->exam_category_id], request()->header('X-Platform'));
        }

        return ApiResponse::ok($this->learning->detail($course, auth('api')->user()));
    }
}
