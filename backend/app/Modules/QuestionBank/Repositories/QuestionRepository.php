<?php

namespace App\Modules\QuestionBank\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Models\Question;
use App\Models\QuestionFolder;
use Illuminate\Database\Eloquent\Builder;

/** @extends BaseRepository<Question> */
class QuestionRepository extends BaseRepository
{
    protected array $sortable = ['id', 'created_at', 'difficulty', 'subject'];

    protected function model(): string
    {
        return Question::class;
    }

    /** Search inside question text (any language). */
    protected function applySearch(Builder $q, ?string $term): void
    {
        if ($term) {
            ctype_digit($term)
                ? $q->where('id', (int) $term)
                : $q->whereHas('translations', fn ($t) => $t->where('text', 'like', '%'.$term.'%'));
        }
    }

    /**
     * filters: folder_id (+ include_sub), label_ids[], label_mode(any|all), subject, topic, difficulty, type, lang (has translation),
     *          missing_lang, not_in_test, used(1|0), created_by
     */
    protected function applyFilters(Builder $q, array $f): void
    {
        if ($folder = $f['folder_id'] ?? null) {
            $ids = ! empty($f['include_sub']) ? $this->folderWithChildren((int) $folder) : [(int) $folder];
            $q->whereIn('folder_id', $ids);
        }
        if ($labels = array_filter((array) ($f['label_ids'] ?? []))) {
            if (($f['label_mode'] ?? 'any') === 'all') {
                foreach ($labels as $l) {
                    $q->whereHas('labels', fn ($w) => $w->where('labels.id', $l));
                }
            } else {
                $q->whereHas('labels', fn ($w) => $w->whereIn('labels.id', $labels));
            }
        }
        $q->when($f['subject'] ?? null, fn ($w, $v) => $w->where('subject', $v))
          ->when($f['topic'] ?? null, fn ($w, $v) => $w->where('topic', $v))
          ->when($f['difficulty'] ?? null, fn ($w, $v) => $w->whereIn('difficulty', (array) $v))
          ->when($f['type'] ?? null, fn ($w, $v) => $w->where('type', $v))
          ->when($f['lang'] ?? null, fn ($w, $v) => $w->whereHas('translations', fn ($t) => $t->where('lang', $v)))
          ->when($f['missing_lang'] ?? null, fn ($w, $v) => $w->whereDoesntHave('translations', fn ($t) => $t->where('lang', $v)))
          ->when($f['not_in_test'] ?? null, fn ($w, $v) => $w->whereDoesntHave('testQuestions', fn ($t) => $t->where('test_id', $v)))
          ->when(isset($f['used']) && $f['used'] !== '', fn ($w) => $f['used'] ? $w->has('testQuestions') : $w->doesntHave('testQuestions'))
          ->when($f['created_by'] ?? null, fn ($w, $v) => $w->where('created_by', $v));
    }

    public function filtered(array $f): Builder
    {
        $q = $this->query();
        $this->applySearch($q, $f['search'] ?? null);
        $this->applyFilters($q, $f);

        return $q;
    }

    public function folderWithChildren(int $id): array
    {
        $ids = [$id];
        $frontier = [$id];
        while ($frontier) {
            $frontier = QuestionFolder::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
