<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Valores monetários passam a ser armazenados em centavos (ex: R$ 100,00 => 10000).
     */
    public function up(): void
    {
        $this->multiplyColumnToCents('lots', 'total_value');
        $this->multiplyColumnToCents('sales', 'total_value');
        $this->multiplyColumnToCents('sales', 'cash_value');
        $this->multiplyColumnToCents('sales', 'down_payment');
        $this->multiplyColumnToCents('sales', 'financed_value');
        $this->multiplyColumnToCents('sales', 'installment_value');
        $this->multiplyColumnToCents('installments', 'value');
    }

    public function down(): void
    {
        $this->divideColumnFromCents('installments', 'value');
        $this->divideColumnFromCents('sales', 'installment_value');
        $this->divideColumnFromCents('sales', 'financed_value');
        $this->divideColumnFromCents('sales', 'down_payment');
        $this->divideColumnFromCents('sales', 'cash_value');
        $this->divideColumnFromCents('sales', 'total_value');
        $this->divideColumnFromCents('lots', 'total_value');
    }

    private function multiplyColumnToCents(string $table, string $column): void
    {
        DB::statement("UPDATE {$table} SET {$column} = ROUND(COALESCE({$column}, 0) * 100) WHERE {$column} IS NOT NULL");
    }

    private function divideColumnFromCents(string $table, string $column): void
    {
        DB::statement("UPDATE {$table} SET {$column} = COALESCE({$column}, 0) / 100 WHERE {$column} IS NOT NULL");
    }
};
