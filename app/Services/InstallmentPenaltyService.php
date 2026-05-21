<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class InstallmentPenaltyService
{
    public const MONTHLY_RATE = 0.025;

    public function daysOverdue(Carbon $dueDate, ?Carbon $paymentDate = null): int
    {
        $paymentDate ??= Carbon::tomorrow()->startOfDay();
        $due = $dueDate->copy()->startOfDay();

        if ($due->greaterThanOrEqualTo($paymentDate)) {
            return 0;
        }

        return (int) $due->diffInDays($paymentDate);
    }

    public function penaltyCents(int $valueCents, int $daysOverdue): int
    {
        if ($daysOverdue <= 0) {
            return 0;
        }

        return (int) round($valueCents * self::MONTHLY_RATE * $daysOverdue / 30);
    }

    public function correctedAmountCents(int $valueCents, int $daysOverdue): int
    {
        return $valueCents + $this->penaltyCents($valueCents, $daysOverdue);
    }

    /**
     * @param  Collection<int, \App\Models\Installment>  $installments
     * @return array{
     *     payment_date: Carbon,
     *     lines: list<array{
     *         number: int,
     *         due_date: string,
     *         days_overdue: int,
     *         value_cents: int,
     *         corrected_cents: int,
     *         value_formatted: string,
     *         corrected_formatted: string
     *     }>,
     *     total_value_cents: int,
     *     total_corrected_cents: int,
     *     max_days_overdue: int,
     *     count: int
     * }
     */
    public function summarize(Collection $installments, ?Carbon $paymentDate = null): array
    {
        $paymentDate ??= Carbon::tomorrow()->startOfDay();
        $fmt = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');

        $lines = [];
        $totalValue = 0;
        $totalCorrected = 0;
        $maxDays = 0;

        foreach ($installments->sortBy('due_date') as $installment) {
            $days = $this->daysOverdue($installment->due_date, $paymentDate);
            $valueCents = (int) $installment->value;
            $correctedCents = $this->correctedAmountCents($valueCents, $days);

            $lines[] = [
                'number' => (int) $installment->number,
                'due_date' => $installment->due_date->format('d/m/Y'),
                'days_overdue' => $days,
                'value_cents' => $valueCents,
                'corrected_cents' => $correctedCents,
                'value_formatted' => $fmt($valueCents),
                'corrected_formatted' => $fmt($correctedCents),
            ];

            $totalValue += $valueCents;
            $totalCorrected += $correctedCents;
            $maxDays = max($maxDays, $days);
        }

        return [
            'payment_date' => $paymentDate,
            'lines' => $lines,
            'total_value_cents' => $totalValue,
            'total_corrected_cents' => $totalCorrected,
            'max_days_overdue' => $maxDays,
            'count' => count($lines),
        ];
    }

    /**
     * @param  array{lines: list<array{number: int, due_date: string, days_overdue: int, value_formatted: string, corrected_formatted: string}>}  $summary
     */
    public function formatLinesForMessage(array $summary): string
    {
        $blocks = [];

        foreach ($summary['lines'] as $line) {
            $daysLabel = $line['days_overdue'] === 1 ? '1 dia' : "{$line['days_overdue']} dias";
            $blocks[] = implode("\n", [
                "• *Parcela ".str_pad((string) $line['number'], 2, '0', STR_PAD_LEFT)."*",
                "  Venc.: {$line['due_date']} · *{$daysLabel}* de atraso",
                "  {$line['value_formatted']} → *{$line['corrected_formatted']}* (prev.)",
            ]);
        }

        return implode("\n\n", $blocks);
    }
}
