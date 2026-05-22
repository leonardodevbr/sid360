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
            $table->string('map_color', 7)->nullable()->after('map_zoom');
        });

        Schema::table('development_streets', function (Blueprint $table) {
            $table->string('color', 7)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('development_streets', function (Blueprint $table) {
            $table->dropColumn('color');
        });

        Schema::table('developments', function (Blueprint $table) {
            $table->dropColumn('map_color');
        });
    }
};
