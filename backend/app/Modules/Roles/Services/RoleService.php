<?php

namespace App\Modules\Roles\Services;

use App\Core\Support\Audit;
use App\Core\Support\DomainException;
use App\Core\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function list()
    {
        return Role::query()->where('guard_name', 'api')->with('permissions:id,name')
            ->withCount('users')->orderBy('id')->get();
    }

    /** Matrix source: modules → actions (with labels). */
    public function catalog(): array
    {
        return collect(PermissionCatalog::MODULES)->map(fn ($actions, $module) => [
            'module' => $module,
            'label' => ucfirst(str_replace('_', ' ', $module)),
            'actions' => array_map(fn ($a) => ['name' => "$module.$a", 'action' => $a, 'label' => ucfirst(str_replace('_', ' ', $a))], $actions),
        ])->values()->all();
    }

    public function create(string $label, array $permissions): Role
    {
        $name = Str::snake(Str::lower($label));
        if (Role::where('name', $name)->where('guard_name', 'api')->exists()) {
            throw new DomainException('A role with this name already exists.');
        }

        return DB::transaction(function () use ($name, $label, $permissions) {
            $role = Role::create(['name' => $name, 'guard_name' => 'api', 'label' => $label, 'is_system' => false]);
            $role->syncPermissions($this->valid($permissions));
            Audit::log('roles.create', null, ['role' => $name]);

            return $role->load('permissions:id,name');
        });
    }

    public function update(Role $role, ?string $label, ?array $permissions): Role
    {
        if ($role->name === 'super_admin') {
            throw new DomainException('Super admin always has every permission.');
        }
        if ($label) {
            $role->update(['label' => $label]);
        }
        if ($permissions !== null) {
            $role->syncPermissions($this->valid($permissions));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles.update', null, ['role' => $role->name, 'permissions' => $permissions]);

        return $role->load('permissions:id,name');
    }

    public function toggle(Role $role, string $permission, bool $on): Role
    {
        if ($role->name === 'super_admin') {
            throw new DomainException('Super admin always has every permission.');
        }
        $this->valid([$permission]) ?: throw new DomainException('Unknown permission.');
        $on ? $role->givePermissionTo($permission) : $role->revokePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles.toggle', null, compact('permission', 'on') + ['role' => $role->name]);

        return $role->load('permissions:id,name');
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw new DomainException('Built-in roles cannot be deleted.');
        }
        if ($role->users()->exists()) {
            throw new DomainException('Move the people in this role to another role first.');
        }
        $role->delete();
    }

    private function valid(array $permissions): array
    {
        return Permission::where('guard_name', 'api')->whereIn('name', $permissions)->pluck('name')->all();
    }
}
