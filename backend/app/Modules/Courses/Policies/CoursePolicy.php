<?php

namespace App\Modules\Courses\Policies;

use App\Models\Course;
use App\Models\User;

/** Role permission (what) + course assignment (where). */
class CoursePolicy
{
    public function view(User $u, Course $c): bool
    {
        return $u->can('courses.view') && $c->isManagedBy($u);
    }

    public function update(User $u, Course $c): bool
    {
        return $u->can('courses.edit') && ($u->isAdmin() || $u->can('courses.all_scope') || $c->staffRole($u) === 'manager');
    }

    public function publish(User $u, Course $c): bool
    {
        return $u->can('courses.publish') && $c->isManagedBy($u);
    }

    public function delete(User $u, Course $c): bool
    {
        return $u->can('courses.delete') && $c->isManagedBy($u);
    }

    public function manageBatches(User $u, Course $c): bool
    {
        return ($u->can('batches.create') || $u->can('batches.edit')) && ($u->isAdmin() || $u->can('courses.all_scope') || $c->staffRole($u) === 'manager');
    }

    /** Teachers assigned to the course can add/edit content. */
    public function manageContent(User $u, Course $c): bool
    {
        return ($u->can('content.create') || $u->can('content.edit')) && $c->isManagedBy($u);
    }
}
