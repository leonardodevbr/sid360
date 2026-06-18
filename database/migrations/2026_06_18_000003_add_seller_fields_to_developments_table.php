<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developments', function (Blueprint $table): void {
            $table->string('seller_name')->nullable()->after('lot_number_pattern');
            $table->string('seller_cpf')->nullable()->after('seller_name');
            $table->string('seller_rg')->nullable()->after('seller_cpf');
            $table->string('seller_rg_issuer')->nullable()->after('seller_rg');
            $table->string('seller_address')->nullable()->after('seller_rg_issuer');
        });
    }

    public function down(): void
    {
        Schema::table('developments', function (Blueprint $table): void {
            $table->dropColumn(['seller_name', 'seller_cpf', 'seller_rg', 'seller_rg_issuer', 'seller_address']);
        });
    }
};
