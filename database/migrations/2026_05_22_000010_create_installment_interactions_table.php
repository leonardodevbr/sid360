<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->nullable()->constrained('installments')->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('phone', 30);
            $table->enum('direction', ['outbound', 'inbound']);
            $table->string('type', 40);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['installment_id', 'direction']);
            $table->index(['phone', 'created_at']);
            $table->index(['sale_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_interactions');
    }
};
