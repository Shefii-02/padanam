<?php

namespace App\Modules\QuestionBank\Services;

use App\Core\Support\DomainException;
use App\Models\Label;
use App\Models\Question;
use App\Models\QuestionFolder;
use Illuminate\Support\Facades\DB;

class FolderLabelService
{
    /** Folder tree with question counts (own + all sub-folders). */
    public function tree(): array
    {
        $folders = QuestionFolder::orderBy('sort')->orderBy('name')->get();
        $counts = Question::selectRaw('folder_id, count(*) c')->groupBy('folder_id')->pluck('c', 'folder_id');
        $byParent = $folders->groupBy(fn ($f) => $f->parent_id ?? 0);
        $build = function ($pid) use (&$build, $byParent, $counts) {
            return ($byParent[$pid] ?? collect())->map(function ($f) use ($build, $counts) {
                $children = $build($f->id);
                $own = (int) ($counts[$f->id] ?? 0);

                return ['id' => $f->id, 'name' => $f->name, 'parent_id' => $f->parent_id, 'own_count' => $own,
                    'count' => $own + array_sum(array_column($children, 'count')), 'children' => $children];
            })->values()->all();
        };

        return ['folders' => $build(0), 'unfiled' => (int) ($counts[''] ?? $counts[null] ?? Question::whereNull('folder_id')->count()), 'total' => Question::count()];
    }

    public function saveFolder(array $d, ?QuestionFolder $f = null): QuestionFolder
    {
        if ($f && ! empty($d['parent_id']) && (int) $d['parent_id'] === $f->id) {
            throw new DomainException("A folder can't be inside itself.");
        }
        if (! $f) {
            $d['owner_id'] = auth()->id();

            return QuestionFolder::create($d);
        }
        $f->update($d);

        return $f;
    }

    public function deleteFolder(QuestionFolder $f): void
    {
        DB::transaction(function () use ($f) {
            Question::where('folder_id', $f->id)->update(['folder_id' => $f->parent_id]);
            QuestionFolder::where('parent_id', $f->id)->update(['parent_id' => $f->parent_id]);
            $f->delete();
        });
    }

    public function labels()
    {
        return Label::withCount('questions')->orderBy('name')->get();
    }

    public function saveLabel(array $d, ?Label $l = null): Label
    {
        return $l ? tap($l)->update($d) : Label::create($d);
    }

    /** Merge label B into A (fix duplicates like "LDC" and "ldc"). */
    public function mergeLabels(Label $keep, Label $remove): Label
    {
        DB::transaction(function () use ($keep, $remove) {
            $ids = DB::table('question_label')->where('label_id', $remove->id)->pluck('question_id');
            foreach ($ids as $qid) {
                DB::table('question_label')->insertOrIgnore(['question_id' => $qid, 'label_id' => $keep->id]);
            }
            $remove->delete();
        });

        return $keep->loadCount('questions');
    }
}
