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
            $table->unsignedBigInteger('base_price_per_m2')->nullable()->after('down_payment_percent');
        });

        Schema::table('development_zones', function (Blueprint $table) {
            $table->unsignedBigInteger('price_per_m2')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('development_zones', function (Blueprint $table) {
            $table->dropColumn('price_per_m2');
        });

        Schema::table('developments', function (Blueprint $table) {
            $table->dropColumn('base_price_per_m2');
        });
    }
};
