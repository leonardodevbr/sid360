<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained('lots')->restrictOnDelete();
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->unique(['sale_id', 'lot_id']);
        });

        // Backfill: toda venda já existente possui exatamente 1 lote (sales.lot_id),
        // que passa a ser também o primeiro registro do pivot. Isso garante que
        // $sale->lots() nunca venha vazio para vendas criadas antes da feature
        // de múltiplos lotes.
        $sales = DB::table('sales')->select('id', 'lot_id')->get();

        foreach ($sales as $sale) {
            DB::table('sale_lots')->insertOrIgnore([
                'sale_id' => $sale->id,
                'lot_id' => $sale->lot_id,
                'order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lots');
    }
};
