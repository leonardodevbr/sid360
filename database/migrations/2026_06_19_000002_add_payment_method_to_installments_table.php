<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table): void {
            if (!Schema::hasColumn('installments', 'payment_method')) {
                $table->string('payment_method', 20)->nullable()->after('status');
            }
            if (!Schema::hasColumn('installments', 'payment_method_description')) {
                $table->string('payment_method_description', 255)->nullable()->after('payment_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table): void {
            $columns = array_filter(
                ['payment_method', 'payment_method_description'],
                fn (string $column): bool => Schema::hasColumn('installments', $column),
            );
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
