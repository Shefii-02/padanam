<?php

namespace App\Modules\Auth\Resources;

use App\Modules\Users\Resources\UserResource;
use Illuminate\Http\Request;

/** Logged-in user + roles + flattened permissions (the React panel filters menus with these). */
class MeResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'role' => $this->primaryRole(),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->hasRole('super_admin')
                ? ['*']
                : $this->getAllPermissions()->pluck('name')->values(),
            'is_panel_user' => $this->isPanelUser(),
            'is_teacher' => $this->hasRole('teacher') || (bool) $this->staffProfile?->is_teacher,
            'profile_completed' => $this->profile_completed_at !== null,
            // app: category slugs the student prepares for (home & store use them)
            'exams' => $this->interests()->with('category:id,slug')->get()->pluck('category.slug')->filter()->unique()->values(),
            'managed_course_ids' => $this->whenLoaded('managedCourses', fn () => $this->managedCourses->pluck('id')),
        ];
    }
}
