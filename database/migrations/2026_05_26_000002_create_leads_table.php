<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained('lots')->cascadeOnDelete();
            $table->foreignId('development_id')->constrained('developments')->cascadeOnDelete();
            $table->string('name');
            $table->string('cpf', 20)->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'contacted', 'converted', 'rejected'])
                ->default('pending');
            $table->string('down_payment_percent', 10)->nullable();
            $table->integer('installments')->nullable();
            $table->integer('simulated_installment_value')->nullable();
            $table->string('utm_source')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('lot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
