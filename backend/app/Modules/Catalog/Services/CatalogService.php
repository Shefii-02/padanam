<?php

namespace App\Modules\Catalog\Services;

use App\Core\Support\DomainException;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Modules\Catalog\DTOs\CategoryData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CatalogService
{
    public function tree(bool $activeOnly = false)
    {
        $q = ExamCategory::query()->whereNull('parent_id')->orderBy('sort')
            ->with(['children' => fn ($c) => $c->when($activeOnly, fn ($w) => $w->where('is_active', true))->withCount('courses'), 'exams'])
            ->withCount('courses');
        if ($activeOnly) {
            $q->where('is_active', true);
        }

        return $q->get();
    }

    public function createCategory(CategoryData $d): ExamCategory
    {
        $data = $d->toArray();
        $data['slug'] = $this->uniqueSlug($d->name);
        $cat = ExamCategory::create($data);
        Cache::forget('catalog.tree');

        return $cat;
    }

    public function updateCategory(ExamCategory $cat, CategoryData $d): ExamCategory
    {
        $data = $d->toArray();
        if (($data['parent_id'] ?? null) === $cat->id) {
            throw new DomainException('A category cannot be its own parent.');
        }
        $cat->update($data);
        Cache::forget('catalog.tree');

        return $cat->fresh();
    }

    public function deleteCategory(ExamCategory $cat): void
    {
        if ($cat->courses()->exists()) {
            throw new DomainException('Move or delete the courses in this category first.');
        }
        $cat->children()->update(['parent_id' => $cat->parent_id]);
        $cat->delete();
        Cache::forget('catalog.tree');
    }

    public function saveExam(array $data, ?Exam $exam = null): Exam
    {
        if (! $exam) {
            $data['slug'] = $this->uniqueSlug($data['name'], Exam::class);

            return Exam::create($data);
        }
        $exam->update($data);

        return $exam->fresh();
    }

    /** reorder: [{id, sort, parent_id}] */
    public function reorder(array $items): void
    {
        foreach ($items as $i) {
            ExamCategory::whereKey($i['id'])->update(['sort' => $i['sort'], 'parent_id' => $i['parent_id'] ?? null]);
        }
        Cache::forget('catalog.tree');
    }

    private function uniqueSlug(string $name, string $model = ExamCategory::class): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(6));
        $slug = $base;
        $n = 2;
        while ($model::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }
}
