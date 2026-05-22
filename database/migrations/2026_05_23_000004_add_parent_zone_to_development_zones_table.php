<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('development_zones', function (Blueprint $table) {
            $table->foreignId('parent_zone_id')
                ->nullable()
                ->after('development_id')
                ->constrained('development_zones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('development_zones', function (Blueprint $table) {
            $table->dropForeign(['parent_zone_id']);
            $table->dropColumn('parent_zone_id');
        });
    }
};
