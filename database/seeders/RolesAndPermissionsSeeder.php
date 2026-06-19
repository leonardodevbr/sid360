<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'developments.view',
            'developments.create',
            'developments.edit',
            'developments.delete',
            'lots.view',
            'lots.create',
            'lots.edit',
            'lots.delete',
            'settings.manage',
            'settings.system',
            'clients.view',
            'clients.create',
            'clients.edit',
            'clients.delete',
            'sales.view',
            'sales.create',
            'sales.edit',
            'sales.delete',
            'sales.cancel',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $allPermissions = Permission::query()->where('guard_name', 'web')->get();

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $adminRole->syncPermissions($allPermissions);

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);
        $superAdminRole->syncPermissions($allPermissions);
    }
}
