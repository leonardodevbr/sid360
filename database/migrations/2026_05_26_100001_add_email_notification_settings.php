<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'email_notifications_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'email'],
            ['key' => 'email_welcome_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'email'],
            ['key' => 'email_reminder_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'email'],
            ['key' => 'email_overdue_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'email'],
        ];

        foreach ($settings as $s) {
            DB::table('settings')->insertOrIgnore([
                'key' => $s['key'],
                'value' => $s['value'],
                'type' => $s['type'],
                'group' => $s['group'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'email_notifications_enabled',
            'email_welcome_enabled',
            'email_reminder_enabled',
            'email_overdue_enabled',
        ])->delete();
    }
};
