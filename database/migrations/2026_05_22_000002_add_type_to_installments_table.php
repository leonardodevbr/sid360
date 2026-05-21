<?php

declare(strict_types=1);

use App\Models\Installment;
use App\Models\Sale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->string('type', 20)->default('financing')->after('sale_id');
            $table->index(['sale_id', 'type']);
        });

        Sale::query()
            ->where('down_payment', '>', 0)
            ->each(function (Sale $sale): void {
                $exists = Installment::query()
                    ->where('sale_id', $sale->id)
                    ->where('type', Installment::TYPE_DOWN_PAYMENT)
                    ->exists();

                if ($exists) {
                    return;
                }

                Installment::query()->create([
                    'sale_id' => $sale->id,
                    'type' => Installment::TYPE_DOWN_PAYMENT,
                    'number' => 0,
                    'due_date' => $sale->sale_date,
                    'value' => $sale->down_payment,
                    'status' => Installment::STATUS_PENDING,
                ]);
            });
    }

    public function down(): void
    {
        Installment::query()
            ->where('type', Installment::TYPE_DOWN_PAYMENT)
            ->delete();

        Schema::table('installments', function (Blueprint $table) {
            $table->dropIndex(['sale_id', 'type']);
            $table->dropColumn('type');
        });
    }
};
