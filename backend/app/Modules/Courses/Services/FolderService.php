<?php

namespace App\Modules\Courses\Services;

use App\Core\Support\DomainException;
use App\Models\Content;
use App\Models\Course;
use App\Models\CourseFolder;
use Illuminate\Support\Facades\DB;

class FolderService
{
    /** Nested folders with content counts (admin tree). */
    public function tree(Course $course): array
    {
        $folders = $course->folders()->withCount('contents')->get();
        $byParent = $folders->groupBy(fn ($f) => $f->parent_id ?? 0);
        $build = function ($parentId) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->map(fn ($f) => [
                'id' => $f->id,
                'title' => $f->title,
                'sort' => $f->sort,
                'batch_ids' => $f->batch_ids,
                'unlock_at' => $f->unlock_at?->toIso8601String(),
                'contents_count' => $f->contents_count,
                'children' => $build($f->id),
            ])->values()->all();
        };

        return $build(0);
    }

    public function create(Course $course, array $d): CourseFolder
    {
        if (! empty($d['parent_id'])) {
            $this->assertInCourse($course, (int) $d['parent_id']);
        }
        $d['course_id'] = $course->id;
        $d['sort'] ??= (int) CourseFolder::where('course_id', $course->id)->where('parent_id', $d['parent_id'] ?? null)->max('sort') + 1;

        return CourseFolder::create($d);
    }

    public function update(CourseFolder $folder, array $d): CourseFolder
    {
        if (array_key_exists('parent_id', $d) && $d['parent_id']) {
            if ((int) $d['parent_id'] === $folder->id || $this->isDescendant($folder, (int) $d['parent_id'])) {
                throw new DomainException("A folder can't be moved inside itself.");
            }
            $this->assertInCourse($folder->course, (int) $d['parent_id']);
        }
        $folder->update($d);

        return $folder->fresh();
    }

    /** Deleting a folder moves its items to the parent (or outside folders) – nothing is lost. */
    public function delete(CourseFolder $folder): void
    {
        DB::transaction(function () use ($folder) {
            Content::where('folder_id', $folder->id)->update(['folder_id' => $folder->parent_id]);
            CourseFolder::where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
            $folder->delete();
        });
    }

    /** items: [{id, parent_id, sort}] */
    public function reorder(Course $course, array $items): void
    {
        DB::transaction(function () use ($course, $items) {
            foreach ($items as $i) {
                CourseFolder::where('course_id', $course->id)->whereKey($i['id'])
                    ->update(['sort' => (int) $i['sort'], 'parent_id' => $i['parent_id'] ?? null]);
            }
        });
    }

    private function assertInCourse(Course $course, int $folderId): void
    {
        if (! CourseFolder::where('course_id', $course->id)->whereKey($folderId)->exists()) {
            throw new DomainException('That folder belongs to another course.');
        }
    }

    private function isDescendant(CourseFolder $folder, int $candidateId): bool
    {
        $current = CourseFolder::find($candidateId);
        while ($current) {
            if ($current->parent_id === $folder->id) {
                return true;
            }
            $current = $current->parent_id ? CourseFolder::find($current->parent_id) : null;
        }

        return false;
    }
}
