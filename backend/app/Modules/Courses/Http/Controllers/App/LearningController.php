<?php

namespace App\Modules\Courses\Http\Controllers\App;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Content;
use App\Models\Course;
use App\Modules\Courses\Services\StudentCourseService;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function __construct(private StudentCourseService $learning) {}

    public function myCourses(Request $r)
    {
        return ApiResponse::ok($this->learning->myCourses($r->user()));
    }

    /** GET /app/courses/{course}/browse?tab=classes&folder_id= */
    public function browse(Request $r, Course $course)
    {
        $tab = $r->query('tab', $this->learning->tabs($course)[0]['key'] ?? 'classes');

        return ApiResponse::ok($this->learning->browse($course, $r->user(), $tab, $r->integer('folder_id') ?: null) + ['tabs' => $this->learning->tabs($course)]);
    }

    public function open(Request $r, Content $content)
    {
        return ApiResponse::ok($this->learning->open($content, $r->user()));
    }

    public function progress(Request $r, Content $content)
    {
        $d = $r->validate(['progress' => 'required|integer|min:0|max:100', 'position' => 'nullable|integer|min:0']);
        $this->learning->open($content, $r->user());   // access check
        $p = $this->learning->saveProgress($content, $r->user(), $d['progress'], $d['position'] ?? 0);

        return ApiResponse::ok(['progress' => $p->progress, 'completed' => (bool) $p->completed_at]);
    }

    /** Per-course class alert on/off (the 5-minute ring). */
    public function classAlerts(Request $r, Course $course)
    {
        $on = $r->boolean('enabled');
        \App\Models\Enrollment::where('user_id', $r->user()->id)->where('course_id', $course->id)->update(['class_alerts' => $on]);

        return ApiResponse::ok(['enabled' => $on], $on ? 'Class alerts on' : 'Class alerts off');
    }
}
