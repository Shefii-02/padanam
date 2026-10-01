<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Modules\Catalog\DTOs\CategoryData;
use App\Modules\Catalog\Resources\CategoryResource;
use App\Modules\Catalog\Resources\ExamResource;
use App\Modules\Catalog\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalog) {}

    // ---- app + public ----
    public function publicTree()
    {
        return ApiResponse::ok(CategoryResource::collection($this->catalog->tree(activeOnly: true)));
    }

    public function exam(Exam $exam)
    {
        return ApiResponse::ok(new ExamResource($exam));
    }

    // ---- admin ----
    public function tree()
    {
        return ApiResponse::ok(CategoryResource::collection($this->catalog->tree()));
    }

    public function store(Request $r)
    {
        $r->validate($this->rules(true));

        return ApiResponse::created(new CategoryResource($this->catalog->createCategory(CategoryData::fromRequest($r))), 'Category added');
    }

    public function update(Request $r, ExamCategory $category)
    {
        $r->validate($this->rules(false));

        return ApiResponse::ok(new CategoryResource($this->catalog->updateCategory($category, CategoryData::fromRequest($r))), 'Saved');
    }

    public function destroy(ExamCategory $category)
    {
        $this->catalog->deleteCategory($category);

        return ApiResponse::ok(null, 'Category deleted');
    }

    public function reorder(Request $r)
    {
        $r->validate(['items' => 'required|array', 'items.*.id' => 'required|integer|exists:exam_categories,id', 'items.*.sort' => 'required|integer']);
        $this->catalog->reorder($r->input('items'));

        return ApiResponse::ok(null, 'Order saved');
    }

    public function storeExam(Request $r)
    {
        return ApiResponse::created(new ExamResource($this->catalog->saveExam($this->examRules($r, true))), 'Exam added');
    }

    public function updateExam(Request $r, Exam $exam)
    {
        return ApiResponse::ok(new ExamResource($this->catalog->saveExam($this->examRules($r, false), $exam)), 'Saved');
    }

    public function destroyExam(Exam $exam)
    {
        $exam->delete();

        return ApiResponse::ok(null, 'Exam deleted');
    }

    private function rules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:60'],
            'parent_id' => ['nullable', 'integer', 'exists:exam_categories,id'],
            'icon' => ['nullable', 'string', 'max:16'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function examRules(Request $r, bool $creating): array
    {
        return $r->validate([
            'exam_category_id' => [$creating ? 'required' : 'sometimes', 'integer', Rule::exists('exam_categories', 'id')],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:80'],
            'full_name' => ['nullable', 'string', 'max:160'],
            'eligibility' => ['nullable', 'array'],
            'pattern' => ['nullable', 'array'],
            'posts' => ['nullable', 'array'],
            'next_exam_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
