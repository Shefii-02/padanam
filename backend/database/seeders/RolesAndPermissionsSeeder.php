<?php

namespace Database\Seeders;

use App\Core\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                Permission::updateOrCreate(
                    ['name' => "$module.$action", 'guard_name' => 'api'],
                    ['module' => $module, 'label' => ucfirst(str_replace('_', ' ', $action))]
                );
            }
        }

        $labels = ['super_admin' => 'Super admin', 'admin' => 'Admin', 'staff' => 'Staff', 'teacher' => 'Teacher', 'student' => 'Student'];
        foreach ($labels as $name => $label) {
            $role = Role::updateOrCreate(['name' => $name, 'guard_name' => 'api'], ['label' => $label, 'is_system' => true]);
            if ($name !== 'super_admin') {
                $role->syncPermissions(PermissionCatalog::forRole($name));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
