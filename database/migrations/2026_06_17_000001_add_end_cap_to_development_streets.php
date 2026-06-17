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
            $table->string('end_cap', 16)->default('round')->after('width');
        });
    }

    public function down(): void
    {
        Schema::table('development_streets', function (Blueprint $table) {
            $table->dropColumn('end_cap');
        });
    }
};
