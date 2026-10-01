<?php

namespace App\Modules\Users\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** @extends BaseRepository<User> */
class UserRepository extends BaseRepository
{
    protected array $searchable = ['name', 'phone', 'email', 'district'];

    protected array $sortable = ['id', 'name', 'created_at', 'last_seen_at'];

    protected function model(): string
    {
        return User::class;
    }

    public function students(): Builder
    {
        return $this->query()->role('student')
            ->withCount(['enrollments as active_enrollments_count' => fn ($q) => $q->active()])
            ->addSelect(['avg_score_percent' => \App\Models\Attempt::query()
                ->selectRaw('AVG(attempts.score / NULLIF(tests.total_marks,0) * 100)')
                ->join('tests', 'tests.id', '=', 'attempts.test_id')
                ->whereColumn('attempts.user_id', 'users.id')
                ->whereNotNull('attempts.submitted_at')]);
    }

    public function staff(): Builder
    {
        return $this->query()->whereHas('roles', fn ($q) => $q->where('name', '!=', 'student'));
    }

    /** Applies search + filters to any user query (used by export). */
    public function filtered(Builder $q, array $filters): Builder
    {
        $this->applySearch($q, $filters['search'] ?? null);
        $this->applyFilters($q, $filters);

        return $q;
    }

    /**
     * filters: status, activity (active|inactive), inactive_days, paid (1|0), category_id, course_id, batch_id,
     *          expiring_days, district, role, never_purchased
     */
    protected function applyFilters(Builder $q, array $f): void
    {
        $q->when($f['status'] ?? null, fn ($w, $v) => $w->where('status', $v))
          ->when($f['district'] ?? null, fn ($w, $v) => $w->where('district', $v))
          ->when($f['role'] ?? null, fn ($w, $v) => $w->role($v))
          ->when(($f['activity'] ?? null) === 'active', fn ($w) => $w->where('last_seen_at', '>=', now()->subDays((int) ($f['inactive_days'] ?? 14))))
          ->when(($f['activity'] ?? null) === 'inactive', fn ($w) => $w->where(fn ($x) => $x->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays((int) ($f['inactive_days'] ?? 14)))))
          ->when(isset($f['paid']) && $f['paid'] !== '', fn ($w) => (int) $f['paid']
                ? $w->whereHas('enrollments', fn ($e) => $e->where('source', 'purchase'))
                : $w->whereDoesntHave('enrollments', fn ($e) => $e->where('source', 'purchase')))
          ->when($f['never_purchased'] ?? null, fn ($w) => $w->whereDoesntHave('orders', fn ($o) => $o->where('status', 'paid')))
          ->when($f['category_id'] ?? null, fn ($w, $v) => $w->whereHas('interests', fn ($i) => $i->where('exam_category_id', $v)))
          ->when($f['course_id'] ?? null, fn ($w, $v) => $w->whereHas('enrollments', fn ($e) => $e->active()->where('course_id', $v)))
          ->when($f['batch_id'] ?? null, fn ($w, $v) => $w->whereHas('enrollments', fn ($e) => $e->active()->where('batch_id', $v)))
          ->when($f['expiring_days'] ?? null, fn ($w, $v) => $w->whereHas('enrollments', fn ($e) => $e->active()->whereBetween('expires_at', [now(), now()->addDays((int) $v)])));
    }
}
