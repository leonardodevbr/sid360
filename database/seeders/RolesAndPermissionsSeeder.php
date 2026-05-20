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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->syncPermissions(Permission::all());
    }
}
