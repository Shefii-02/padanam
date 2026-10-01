<?php

namespace App\Modules\Courses\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;

/** @extends BaseRepository<Course> */
class CourseRepository extends BaseRepository
{
    protected array $searchable = ['title', 'slug'];

    protected array $sortable = ['id', 'title', 'created_at', 'students_count', 'sort'];

    protected function model(): string
    {
        return Course::class;
    }

    protected function applyFilters(Builder $q, array $f): void
    {
        $q->when($f['status'] ?? null, fn ($w, $v) => $w->where('status', $v))
          ->when($f['category_id'] ?? null, fn ($w, $v) => $w->where(fn ($x) => $x->where('exam_category_id', $v)
                ->orWhereIn('exam_category_id', fn ($s) => $s->select('id')->from('exam_categories')->where('parent_id', $v))))
          ->when($f['course_type'] ?? null, fn ($w, $v) => $w->where('course_type', $v))
          ->when(($f['pricing'] ?? null) === 'free', fn ($w) => $w->whereHas('batches', fn ($b) => $b->where('is_free', true)))
          ->when(($f['pricing'] ?? null) === 'paid', fn ($w) => $w->whereHas('batches', fn ($b) => $b->where('is_free', false)))
          ->when($f['teacher_id'] ?? null, fn ($w, $v) => $w->whereHas('staff', fn ($s) => $s->where('users.id', $v)));
    }

    /** Store listing for the app: published courses with at least one purchasable batch. */
    public function store(array $f): Builder
    {
        return $this->query()->published()
            ->with(['category:id,name,slug', 'batches' => fn ($b) => $b->where('status', 'active')->orderBy('price')])
            ->when($f['category'] ?? null, fn ($w, $v) => $w->whereHas('category', fn ($c) => $c->where('slug', $v)->orWhere('id', $v)
                ->orWhereHas('parent', fn ($p) => $p->where('slug', $v)->orWhere('id', $v))))
            ->when($f['search'] ?? null, fn ($w, $v) => $w->where('title', 'like', "%$v%"))
            ->when(($f['pricing'] ?? null) === 'free', fn ($w) => $w->whereHas('batches', fn ($b) => $b->where('is_free', true)))
            ->orderByDesc('is_featured')->orderBy('sort')->orderByDesc('students_count');
    }
}
