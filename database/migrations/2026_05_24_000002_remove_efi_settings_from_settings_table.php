<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'efi')
            ->orWhere('key', 'like', 'efi\_%')
            ->delete();

        Cache::forget('settings.all');
    }

    public function down(): void
    {
        // EFI settings moved to .env — no rollback.
    }
};
