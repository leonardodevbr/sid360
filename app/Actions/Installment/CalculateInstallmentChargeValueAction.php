<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use Carbon\Carbon;

class CalculateInstallmentChargeValueAction
{
    /** Multa de 2% (mesmo padrão Efi: fine => 200). */
    private const FINE_BPS = 200;

    /** Juros de 0,33% ao dia (mesmo padrão Efi: interest => 33). */
    private const INTEREST_BPS_PER_DAY = 33;

    /**
     * @return array{
     *     original_value: int,
     *     fine_cents: int,
     *     interest_cents: int,
     *     total_value: int,
     *     days_overdue: int,
     *     is_overdue: bool,
     * }
     */
    public function execute(
        Installment $installment,
        bool $waivePenalties,
        ?string $referenceDate = null,
    ): array {
        $originalValue = (int) $installment->value;
        $isOverdue = $installment->isOverdue();

        if ($waivePenalties || ! $isOverdue || $installment->due_date === null) {
            return [
                'original_value' => $originalValue,
                'fine_cents' => 0,
                'interest_cents' => 0,
                'total_value' => $originalValue,
                'days_overdue' => 0,
                'is_overdue' => $isOverdue,
            ];
        }

        $reference = Carbon::parse($referenceDate ?? now()->toDateString())->startOfDay();
        $dueDate = $installment->due_date->copy()->startOfDay();
        $daysOverdue = $reference->lte($dueDate)
            ? 0
            : (int) $dueDate->diffInDays($reference);

        $fineCents = (int) round($originalValue * self::FINE_BPS / 10000);
        $interestCents = (int) round($originalValue * self::INTEREST_BPS_PER_DAY / 10000 * $daysOverdue);
        $totalValue = $originalValue + $fineCents + $interestCents;

        return [
            'original_value' => $originalValue,
            'fine_cents' => $fineCents,
            'interest_cents' => $interestCents,
            'total_value' => $totalValue,
            'days_overdue' => $daysOverdue,
            'is_overdue' => $isOverdue,
        ];
    }
}
