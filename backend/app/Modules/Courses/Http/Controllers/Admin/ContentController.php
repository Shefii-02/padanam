<?php

namespace App\Modules\Courses\Http\Controllers\Admin;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Content;
use App\Models\Course;
use App\Modules\Courses\DTOs\ContentData;
use App\Modules\Courses\Http\Requests\ContentRequest;
use App\Modules\Courses\Resources\ContentResource;
use App\Modules\Courses\Services\ContentService;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(private ContentService $contents) {}

    /** ?folder_id=12  or ?outside=1 for items outside folders */
    public function index(Request $r, Course $course)
    {
        $this->authorize('view', $course);

        return ApiResponse::ok(ContentResource::collection($this->contents->list($course, $r->integer('folder_id') ?: null, $r->boolean('outside'))));
    }

    public function store(ContentRequest $r, Course $course)
    {
        $this->authorize('manageContent', $course);

        return ApiResponse::created(new ContentResource($this->contents->create($course, ContentData::fromRequest($r))), 'Content added');
    }

    public function update(ContentRequest $r, Content $content)
    {
        $this->authorize('manageContent', $content->course);

        return ApiResponse::ok(new ContentResource($this->contents->update($content, ContentData::fromRequest($r))), 'Saved');
    }

    public function destroy(Request $r, Content $content)
    {
        $this->authorize('manageContent', $content->course);
        abort_unless($r->user()->can('content.delete'), 403);
        $this->contents->delete($content);

        return ApiResponse::ok(null, 'Content deleted');
    }

    public function reorder(Request $r, Course $course)
    {
        $this->authorize('manageContent', $course);
        $r->validate(['items' => 'required|array', 'items.*.id' => 'required|integer', 'items.*.sort' => 'required|integer', 'items.*.folder_id' => 'nullable|integer']);
        $this->contents->reorder($course, $r->input('items'));

        return ApiResponse::ok(null, 'Order saved');
    }
}
