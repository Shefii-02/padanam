<?php

namespace App\Modules\Courses\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Course;
use App\Models\CourseFolder;
use App\Modules\Courses\Services\FolderService;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    public function __construct(private FolderService $folders) {}

    public function index(Course $course)
    {
        $this->authorize('view', $course);

        return ApiResponse::ok($this->folders->tree($course));
    }

    public function store(Request $r, Course $course)
    {
        $this->authorize('manageContent', $course);

        return ApiResponse::created($this->folders->create($course, $this->rules($r, true)), 'Folder added');
    }

    public function update(Request $r, CourseFolder $folder)
    {
        $this->authorize('manageContent', $folder->course);

        return ApiResponse::ok($this->folders->update($folder, $this->rules($r, false)), 'Folder saved');
    }

    public function destroy(CourseFolder $folder)
    {
        $this->authorize('manageContent', $folder->course);
        $this->folders->delete($folder);

        return ApiResponse::ok(null, 'Folder deleted. Its items moved up one level');
    }

    public function reorder(Request $r, Course $course)
    {
        $this->authorize('manageContent', $course);
        $r->validate(['items' => 'required|array', 'items.*.id' => 'required|integer', 'items.*.sort' => 'required|integer', 'items.*.parent_id' => 'nullable|integer']);
        $this->folders->reorder($course, $r->input('items'));

        return ApiResponse::ok($this->folders->tree($course), 'Order saved');
    }

    private function rules(Request $r, bool $creating): array
    {
        return $r->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:course_folders,id'],
            'sort' => ['nullable', 'integer'],
            'batch_ids' => ['nullable', 'array'],
            'batch_ids.*' => ['integer', 'exists:batches,id'],
            'unlock_at' => ['nullable', 'date'],
        ]);
    }
}
