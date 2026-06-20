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
        //
        // IMPORTANTE: client_id é FK (constrained()) e o MySQL exige que sempre
        // exista algum índice com a FK como coluna líder. Por isso o índice NOVO
        // é criado ANTES de remover o antigo — se a ordem for invertida e o antigo
        // for o único cobrindo a FK no momento, o DROP INDEX falha com erro 1553
        // ("needed in a foreign key constraint"), como aconteceu em produção.
        if (!Schema::hasIndex('client_documents', ['client_id', 'type', 'side', 'is_current'])) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->index(['client_id', 'type', 'side', 'is_current']);
            });
        }

        if (Schema::hasIndex('client_documents', ['client_id', 'type', 'is_current'])) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->dropIndex(['client_id', 'type', 'is_current']);
            });
        }

        // Mesmo raciocínio para sale_id (FK em sale_documents).
        if (!Schema::hasIndex('sale_documents', ['sale_id', 'type', 'side'])) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->index(['sale_id', 'type', 'side']);
            });
        }

        if (Schema::hasIndex('sale_documents', ['sale_id', 'type'])) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->dropIndex(['sale_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        // Mesma regra de ordem: recria o índice antigo (que também cobre a FK)
        // antes de remover o novo, senão o DROP INDEX do novo pode ser o único
        // cobrindo a FK no momento e falhar com erro 1553.
        if (!Schema::hasIndex('client_documents', ['client_id', 'type', 'is_current'])) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->index(['client_id', 'type', 'is_current']);
            });
        }

        if (Schema::hasIndex('client_documents', ['client_id', 'type', 'side', 'is_current'])) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->dropIndex(['client_id', 'type', 'side', 'is_current']);
            });
        }

        if (Schema::hasColumn('client_documents', 'side')) {
            Schema::table('client_documents', function (Blueprint $table): void {
                $table->dropColumn('side');
            });
        }

        if (!Schema::hasIndex('sale_documents', ['sale_id', 'type'])) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->index(['sale_id', 'type']);
            });
        }

        if (Schema::hasIndex('sale_documents', ['sale_id', 'type', 'side'])) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->dropIndex(['sale_id', 'type', 'side']);
            });
        }

        if (Schema::hasColumn('sale_documents', 'side')) {
            Schema::table('sale_documents', function (Blueprint $table): void {
                $table->dropColumn('side');
            });
        }
    }
};
