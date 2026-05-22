<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@sid360.com.br'],
            [
                'name' => 'Administrador Sid360',
                'username' => 'admin',
                'password' => Hash::make('123$qweR---'),
            ]
        );

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ]);
        $superAdmin->syncRoles([$superAdminRole]);

        $this->command->info('Usuário admin@sid360.com.br criado (senha: 123$qweR---).');
    }
}
