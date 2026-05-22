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
            $table->json('coordinates')->nullable()->after('location');
            $table->string('lot_number_pattern')->nullable()->after('coordinates');
            $table->json('map_center')->nullable()->after('lot_number_pattern');
            $table->integer('map_zoom')->default(17)->after('map_center');
        });
    }

    public function down(): void
    {
        Schema::table('developments', function (Blueprint $table) {
            $table->dropColumn(['coordinates', 'lot_number_pattern', 'map_center', 'map_zoom']);
        });
    }
};
