<?php

namespace App\Modules\QuestionBank\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Label;
use App\Models\QuestionFolder;
use App\Modules\QuestionBank\Services\FolderLabelService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FolderLabelController extends Controller
{
    public function __construct(private FolderLabelService $service) {}

    public function folders()
    {
        return ApiResponse::ok($this->service->tree());
    }

    public function storeFolder(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:80', 'parent_id' => 'nullable|integer|exists:question_folders,id']);

        return ApiResponse::created($this->service->saveFolder($d), 'Folder added');
    }

    public function updateFolder(Request $r, QuestionFolder $folder)
    {
        $d = $r->validate(['name' => 'sometimes|string|max:80', 'parent_id' => 'nullable|integer|exists:question_folders,id', 'sort' => 'nullable|integer']);

        return ApiResponse::ok($this->service->saveFolder($d, $folder), 'Saved');
    }

    public function destroyFolder(QuestionFolder $folder)
    {
        $this->service->deleteFolder($folder);

        return ApiResponse::ok(null, 'Folder deleted. Questions moved up one level');
    }

    public function labels()
    {
        return ApiResponse::ok($this->service->labels());
    }

    public function storeLabel(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:50|unique:labels,name', 'color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/']);

        return ApiResponse::created($this->service->saveLabel($d), 'Label added');
    }

    public function updateLabel(Request $r, Label $label)
    {
        $d = $r->validate(['name' => ['sometimes', 'string', 'max:50', Rule::unique('labels', 'name')->ignore($label->id)], 'color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/']);

        return ApiResponse::ok($this->service->saveLabel($d, $label), 'Saved');
    }

    public function destroyLabel(Label $label)
    {
        $label->delete();

        return ApiResponse::ok(null, 'Label deleted');
    }

    public function mergeLabel(Request $r, Label $label)
    {
        $r->validate(['into_id' => 'required|integer|exists:labels,id|not_in:'.$label->id]);

        return ApiResponse::ok($this->service->mergeLabels(Label::findOrFail($r->input('into_id')), $label), 'Labels merged');
    }
}
