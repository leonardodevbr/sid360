<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('lots')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->date('sale_date');
            $table->decimal('total_value', 14, 2);
            $table->decimal('cash_value', 14, 2)->nullable();
            $table->decimal('down_payment', 14, 2)->default(0);
            $table->decimal('financed_value', 14, 2);
            $table->integer('installments_count');
            $table->decimal('installment_value', 14, 2);
            $table->date('first_due_date');
            $table->integer('payment_day');
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
