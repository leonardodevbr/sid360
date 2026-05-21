<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->timestamp('whatsapp_reminder_sent_at')->nullable()->after('status');
            $table->timestamp('whatsapp_overdue_sent_at')->nullable()->after('whatsapp_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_reminder_sent_at', 'whatsapp_overdue_sent_at']);
        });
    }
};
