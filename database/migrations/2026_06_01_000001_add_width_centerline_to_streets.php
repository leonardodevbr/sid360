<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('development_streets', function (Blueprint $table) {
            $table->decimal('width', 6, 2)->nullable()->after('color');
            $table->json('centerline')->nullable()->after('coordinates');
        });
    }

    public function down(): void
    {
        Schema::table('development_streets', function (Blueprint $table) {
            $table->dropColumn(['width', 'centerline']);
        });
    }
};
