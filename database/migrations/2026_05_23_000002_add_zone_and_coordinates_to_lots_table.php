<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->foreignId('zone_id')->nullable()->after('development_id')
                ->constrained('development_zones')->nullOnDelete();
            $table->json('coordinates')->nullable()->after('status');
            $table->decimal('area_computed', 10, 2)->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropForeign(['zone_id']);
            $table->dropColumn(['zone_id', 'coordinates', 'area_computed']);
        });
    }
};
