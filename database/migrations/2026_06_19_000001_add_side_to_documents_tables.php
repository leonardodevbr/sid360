<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotente de propósito: em produção essa migration já chegou a rodar
     * parcialmente (DDL no MySQL não é transacional — cada ALTER é commitado
     * na hora), travou em algum passo e nunca foi registrada em `migrations`.
     * Cada bloco abaixo checa o estado atual antes de agir, então o `migrate`
     * completa só o que falta, em qualquer ponto que a tentativa anterior
     * tenha parado.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('client_documents', 'side')) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->string('side', 20)->default('aberto')->after('type');
            });
        }

        if (!Schema::hasColumn('sale_documents', 'side')) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->string('side', 20)->default('aberto')->after('type');
            });
        }

        // O índice antigo (client_id, type, is_current) não cobre múltiplos lados
        // por tipo — recriado incluindo 'side' para refletir corretamente o que é
        // "atual" por combinação tipo+lado.
        if (Schema::hasIndex('client_documents', ['client_id', 'type', 'is_current'])) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->dropIndex(['client_id', 'type', 'is_current']);
            });
        }

        if (!Schema::hasIndex('client_documents', ['client_id', 'type', 'side', 'is_current'])) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->index(['client_id', 'type', 'side', 'is_current']);
            });
        }

        if (Schema::hasIndex('sale_documents', ['sale_id', 'type'])) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->dropIndex(['sale_id', 'type']);
            });
        }

        if (!Schema::hasIndex('sale_documents', ['sale_id', 'type', 'side'])) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->index(['sale_id', 'type', 'side']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('client_documents', ['client_id', 'type', 'side', 'is_current'])) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->dropIndex(['client_id', 'type', 'side', 'is_current']);
            });
        }

        if (!Schema::hasIndex('client_documents', ['client_id', 'type', 'is_current'])) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->index(['client_id', 'type', 'is_current']);
            });
        }

        if (Schema::hasColumn('client_documents', 'side')) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->dropColumn('side');
            });
        }

        if (Schema::hasIndex('sale_documents', ['sale_id', 'type', 'side'])) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->dropIndex(['sale_id', 'type', 'side']);
            });
        }

        if (!Schema::hasIndex('sale_documents', ['sale_id', 'type'])) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->index(['sale_id', 'type']);
            });
        }

        if (Schema::hasColumn('sale_documents', 'side')) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->dropColumn('side');
            });
        }
    }
};
