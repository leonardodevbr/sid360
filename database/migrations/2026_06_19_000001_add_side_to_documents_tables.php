<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_documents', function (Blueprint $table): void {
            $table->string('side', 20)->default('aberto')->after('type');
        });

        Schema::table('sale_documents', function (Blueprint $table): void {
            $table->string('side', 20)->default('aberto')->after('type');
        });

        // O índice antigo (client_id, type, is_current) não cobre múltiplos lados
        // por tipo — recriado incluindo 'side' para refletir corretamente o que é
        // "atual" por combinação tipo+lado.
        Schema::table('client_documents', function (Blueprint $table): void {
            $table->dropIndex(['client_id', 'type', 'is_current']);
            $table->index(['client_id', 'type', 'side', 'is_current']);
        });

        Schema::table('sale_documents', function (Blueprint $table): void {
            $table->dropIndex(['sale_id', 'type']);
            $table->index(['sale_id', 'type', 'side']);
        });
    }

    public function down(): void
    {
        Schema::table('client_documents', function (Blueprint $table): void {
            $table->dropIndex(['client_id', 'type', 'side', 'is_current']);
            $table->index(['client_id', 'type', 'is_current']);
            $table->dropColumn('side');
        });

        Schema::table('sale_documents', function (Blueprint $table): void {
            $table->dropIndex(['sale_id', 'type', 'side']);
            $table->index(['sale_id', 'type']);
            $table->dropColumn('side');
        });
    }
};
