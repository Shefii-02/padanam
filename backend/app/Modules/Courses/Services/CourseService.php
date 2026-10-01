<?php

namespace App\Modules\Courses\Services;

use App\Core\Support\Audit;
use App\Core\Support\DomainException;
use App\Core\Support\Money;
use App\Models\Course;
use App\Models\User;
use App\Modules\Courses\DTOs\CourseData;
use App\Modules\Courses\Repositories\CourseRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseService
{
    public function __construct(private CourseRepository $courses, private BatchService $batches) {}

    /** A new course always gets a Default batch so simple courses feel batch-free. */
    public function create(CourseData $d, User $by): Course
    {
        return DB::transaction(function () use ($d, $by) {
            $cols = $d->courseColumns();
            $cols['slug'] = $this->uniqueSlug($d->title);
            $cols['course_type'] ??= 'live_recorded';
            $cols['features'] = array_merge(Course::presetFeatures($cols['course_type']), $d->features ?? []);
            $cols['created_by'] = $by->id;
            $course = $this->courses->create($cols);

            $this->batches->createDefault($course, Money::toPaise($d->price ?? 0), Money::toPaise($d->mrp ?? $d->price ?? 0));

            // the creator manages it unless they are an admin (admins see everything anyway)
            $staff = $d->staff ?? [];
            if (! $by->isAdmin() && ! collect($staff)->contains('user_id', $by->id)) {
                $staff[] = ['user_id' => $by->id, 'role' => 'manager'];
            }
            $this->syncStaff($course, $staff);
            Audit::log('courses.create', $course);

            return $course->load('batches', 'staff:id,name', 'category:id,name');
        });
    }

    public function update(Course $course, CourseData $d): Course
    {
        $cols = $d->courseColumns();
        if (isset($cols['features'])) {
            $cols['features'] = array_merge($course->features ?? [], array_map('boolval', $cols['features']));
        }
        if (isset($cols['course_type']) && ! isset($d->toArray()['features']) && $cols['course_type'] !== $course->course_type) {
            $cols['features'] = Course::presetFeatures($cols['course_type']);
        }
        $this->courses->update($course, $cols);
        if ($d->staff !== null) {
            $this->syncStaff($course, $d->staff);
        }

        return $course->fresh(['batches', 'staff:id,name', 'category:id,name']);
    }

    /** staff: [{user_id, role: manager|teacher}] – they manage/teach without buying. */
    public function syncStaff(Course $course, array $staff): void
    {
        $sync = [];
        foreach ($staff as $s) {
            $sync[(int) $s['user_id']] = ['role' => in_array($s['role'] ?? 'teacher', ['manager', 'teacher'], true) ? $s['role'] : 'teacher'];
        }
        $course->staff()->sync($sync);
    }

    public function setStatus(Course $course, string $status): Course
    {
        if ($status === 'published') {
            if (! $course->batches()->where('status', 'active')->exists()) {
                throw new DomainException('Add at least one active batch before publishing.');
            }
            $course->published_at ??= now();
        }
        $course->status = $status;
        $course->save();
        Audit::log('courses.'.$status, $course);

        return $course;
    }

    public function delete(Course $course): void
    {
        if ($course->enrollments()->where('status', 'active')->exists()) {
            throw new DomainException('This course has active students. Archive it instead.');
        }
        $course->delete();
        Audit::log('courses.delete', $course);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: Str::lower(Str::random(6));
        $slug = $base;
        $i = 2;
        while (Course::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
