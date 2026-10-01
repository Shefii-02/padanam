<?php

namespace App\Modules\Users\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Models\User;
use App\Modules\Users\DTOs\StaffData;
use App\Modules\Users\Http\Requests\StaffRequest;
use App\Modules\Users\Repositories\UserRepository;
use App\Modules\Users\Resources\UserResource;
use App\Modules\Users\Services\UserAdminService;
use Illuminate\Http\Request;

class StaffAdminController extends Controller
{
    public function __construct(private UserRepository $users, private UserAdminService $service) {}

    public function index(Request $r)
    {
        $page = $this->users->paginate($r->all(), ['roles:id,name', 'staffProfile', 'managedCourses:id,title'], $this->users->staff());

        return ApiResponse::ok(UserResource::collection($page));
    }

    /** Lightweight picker for "assign teachers" multi-selects. */
    public function options(Request $r)
    {
        $q = $this->users->staff()->select('id', 'name')->with('roles:id,name');
        if ($r->boolean('teachers_only')) {
            $q->role('teacher');
        }

        return ApiResponse::ok($q->orderBy('name')->get()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'role' => $u->roles->first()?->name]));
    }

    public function store(StaffRequest $r)
    {
        return ApiResponse::created(new UserResource($this->service->createStaff(StaffData::fromRequest($r))), 'Staff member added');
    }

    public function update(StaffRequest $r, User $user)
    {
        return ApiResponse::ok(new UserResource($this->service->updateStaff($user, StaffData::fromRequest($r))), 'Saved');
    }

    public function block(User $user)
    {
        return ApiResponse::ok(new UserResource($this->service->setStatus($user, 'blocked')), 'Blocked');
    }

    public function unblock(User $user)
    {
        return ApiResponse::ok(new UserResource($this->service->setStatus($user, 'active')), 'Active again');
    }
}
