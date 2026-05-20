<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Settings::set('app_name', 'Sid360', 'string', 'general');
        Settings::set('allowed_login_methods', ['email', 'username'], 'json', 'auth');

        $this->command->info('Configurações iniciais criadas.');
    }
}
