<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
            $table->string('role')->default('buyer');
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->unique(['sale_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_clients');
    }
};
