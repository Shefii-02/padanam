<?php

namespace App\Core\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Queries only – no business rules. Services call repositories.
 *
 * @template T of Model
 */
abstract class BaseRepository
{
    /** @return class-string<T> */
    abstract protected function model(): string;

    /** Columns the admin list can search with ?search= */
    protected array $searchable = [];

    /** ?sort= values allowed (prefix with - for desc) */
    protected array $sortable = ['id', 'created_at'];

    public function query(): Builder
    {
        return ($this->model())::query();
    }

    /** @return T */
    public function find(int|string $id, array $with = []): Model
    {
        return $this->query()->with($with)->findOrFail($id);
    }

    /** @return T */
    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    /** @return T */
    public function update(Model $model, array $data): Model
    {
        $model->fill($data)->save();

        return $model->refresh();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    public function paginate(array $filters = [], array $with = [], ?Builder $query = null): LengthAwarePaginator
    {
        $q = ($query ?? $this->query())->with($with);
        $this->applySearch($q, $filters['search'] ?? null);
        $this->applyFilters($q, $filters);
        $this->applySort($q, $filters['sort'] ?? '-id');

        return $q->paginate(min((int) ($filters['per_page'] ?? 20), 100))->withQueryString();
    }

    /** Override in subclasses for module filters. */
    protected function applyFilters(Builder $q, array $filters): void {}

    protected function applySearch(Builder $q, ?string $term): void
    {
        if (! $term || ! $this->searchable) {
            return;
        }
        $q->where(function (Builder $w) use ($term) {
            foreach ($this->searchable as $col) {
                $w->orWhere($col, 'like', '%'.$term.'%');
            }
        });
    }

    protected function applySort(Builder $q, string $sort): void
    {
        $dir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $col = ltrim($sort, '-');
        if (in_array($col, $this->sortable, true)) {
            $q->orderBy($col, $dir);
        }
    }
}
