<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use Carbon\Carbon;

class PayInstallmentAction
{
    public function execute(Installment $installment, ?string $paidAt = null): Installment
    {
        $installment->update([
            'paid_at' => $paidAt ? Carbon::parse($paidAt) : Carbon::today(),
            'status' => Installment::STATUS_PAID,
        ]);

        return $installment->fresh();
    }
}
