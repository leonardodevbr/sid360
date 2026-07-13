<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->json('faces')->nullable()->after('size_label');
            $table->text('contract_measures_text')->nullable()->after('faces');
            $table->string('size_label', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->dropColumn(['faces', 'contract_measures_text']);
            $table->string('size_label', 50)->nullable()->change();
        });
    }
};
