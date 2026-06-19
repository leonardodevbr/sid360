<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use Carbon\Carbon;

class PayInstallmentAction
{
    public function execute(
        Installment $installment,
        ?string $paidAt = null,
        ?string $paymentMethod = null,
        ?string $paymentMethodDescription = null,
    ): Installment {
        $installment->update([
            'paid_at' => $paidAt ? Carbon::parse($paidAt) : Carbon::today(),
            'status' => Installment::STATUS_PAID,
            'payment_method' => $paymentMethod,
            'payment_method_description' => in_array($paymentMethod, Installment::PAYMENT_METHODS_REQUIRING_DESCRIPTION, true)
                ? $paymentMethodDescription
                : null,
        ]);

        return $installment->fresh();
    }
}
