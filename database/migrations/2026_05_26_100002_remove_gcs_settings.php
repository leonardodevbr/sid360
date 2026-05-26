<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->whereIn('key', [
            'gcs_key_file_path',
            'gcs_project_id',
            'gcs_bucket',
        ])->delete();
    }

    public function down(): void
    {
        // Não restaurar — se precisar reverter, rodar o SettingSeeder manualmente
    }
};
