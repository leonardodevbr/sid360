<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use Carbon\Carbon;

class UpdateInstallmentDueDateAction
{
    /**
     * Altera a data de vencimento de uma parcela pendente/atrasada, fora do
     * fluxo de geração de boleto/PIX (que já permite informar a data na hora
     * de gerar a cobrança). Não se aplica a parcelas já pagas.
     */
    public function execute(Installment $installment, string $dueDate): Installment
    {
        $installment->update([
            'due_date' => Carbon::parse($dueDate),
        ]);

        return $installment->fresh();
    }
}
