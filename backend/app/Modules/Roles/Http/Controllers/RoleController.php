<?php

namespace App\Modules\Roles\Http\Controllers;

use App\Core\Http\ApiResponse;
use App\Core\Http\Controller;
use App\Modules\Roles\Resources\RoleResource;
use App\Modules\Roles\Services\RoleService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index()
    {
        return ApiResponse::ok(RoleResource::collection($this->roles->list()));
    }

    public function catalog()
    {
        return ApiResponse::ok($this->roles->catalog());
    }

    public function store(Request $r)
    {
        $d = $r->validate(['label' => 'required|string|max:40', 'permissions' => 'array', 'permissions.*' => 'string']);

        return ApiResponse::created(new RoleResource($this->roles->create($d['label'], $d['permissions'] ?? [])), 'Role created');
    }

    public function update(Request $r, Role $role)
    {
        $d = $r->validate(['label' => 'sometimes|string|max:40', 'permissions' => 'sometimes|array', 'permissions.*' => 'string']);

        return ApiResponse::ok(new RoleResource($this->roles->update($role, $d['label'] ?? null, $d['permissions'] ?? null)), 'Role saved');
    }

    public function toggle(Request $r, Role $role)
    {
        $d = $r->validate(['permission' => 'required|string', 'enabled' => 'required|boolean']);

        return ApiResponse::ok(new RoleResource($this->roles->toggle($role, $d['permission'], $d['enabled'])), 'Permission updated');
    }

    public function destroy(Role $role)
    {
        $this->roles->delete($role);

        return ApiResponse::ok(null, 'Role deleted');
    }
}
