<?php

namespace App\Modules\Courses\Services;

use App\Models\Content;
use App\Models\Course;
use App\Models\CourseFolder;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Answers "can this person open this?" in one place.
 *  - staff/teachers assigned to the course (or admins) → everything, no purchase needed
 *  - enrolled students with an active enrollment → items visible to their batch
 *  - anyone → free and demo items
 */
class CourseAccessService
{
    /** @var array<string, Collection> */
    private array $cache = [];

    public function activeEnrollments(User $user, Course $course): Collection
    {
        return $this->cache[$user->id.':'.$course->id] ??= Enrollment::query()
            ->where('user_id', $user->id)->where('course_id', $course->id)->active()->get();
    }

    public function isStaff(?User $user, Course $course): bool
    {
        return $user !== null && $user->isPanelUser() && $course->isManagedBy($user);
    }

    public function isEnrolled(?User $user, Course $course): bool
    {
        return $user !== null && $this->activeEnrollments($user, $course)->isNotEmpty();
    }

    public function batchIds(?User $user, Course $course): array
    {
        return $user ? $this->activeEnrollments($user, $course)->pluck('batch_id')->all() : [];
    }

    public function canOpen(?User $user, Content $content): bool
    {
        $course = $content->course;
        if ($this->isStaff($user, $course)) {
            return true;
        }
        if ($content->publish_at && $content->publish_at->isFuture()) {
            return false;
        }
        if ($content->isFreeToWatch()) {
            return true;
        }
        if (! $this->isEnrolled($user, $course)) {
            return false;
        }
        if ($content->batch_ids && ! array_intersect($content->batch_ids, $this->batchIds($user, $course))) {
            return false;
        }
        if ($content->folder_id && ! $this->folderUnlocked($content->folder, $this->batchIds($user, $course))) {
            return false;
        }
        if ($content->unlock_after_content_id) {
            return \App\Models\ContentProgress::where('user_id', $user->id)
                ->where('content_id', $content->unlock_after_content_id)->whereNotNull('completed_at')->exists();
        }

        return true;
    }

    /** Human reason for a locked item (shown on the lock icon). */
    public function lockReason(?User $user, Content $content): ?string
    {
        if ($this->canOpen($user, $content)) {
            return null;
        }
        if ($content->publish_at && $content->publish_at->isFuture()) {
            return 'Opens on '.$content->publish_at->format('j M, g:i A');
        }
        if (! $this->isEnrolled($user, $content->course)) {
            return 'Join the course to unlock';
        }
        if ($content->folder?->unlock_at?->isFuture()) {
            return 'Opens on '.$content->folder->unlock_at->format('j M');
        }
        if ($content->unlock_after_content_id) {
            return 'Finish “'.($content->unlockAfter?->title ?? 'the previous item').'” first';
        }

        return 'Not part of your batch';
    }

    public function folderUnlocked(?CourseFolder $folder, array $batchIds): bool
    {
        if (! $folder) {
            return true;
        }
        if ($folder->unlock_at && $folder->unlock_at->isFuture()) {
            return false;
        }
        if ($folder->batch_ids && ! array_intersect($folder->batch_ids, $batchIds)) {
            return false;
        }

        return true;
    }
}
