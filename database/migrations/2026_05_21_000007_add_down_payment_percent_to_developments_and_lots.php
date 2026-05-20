<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developments', function (Blueprint $table) {
            $table->decimal('down_payment_percent', 5, 2)->default(20)->after('status');
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->decimal('down_payment_percent', 5, 2)->nullable()->after('total_value');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn('down_payment_percent');
        });

        Schema::table('developments', function (Blueprint $table) {
            $table->dropColumn('down_payment_percent');
        });
    }
};
