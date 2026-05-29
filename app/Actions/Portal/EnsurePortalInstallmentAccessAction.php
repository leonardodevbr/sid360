<?php

declare(strict_types=1);

namespace App\Actions\Portal;

use App\Models\Installment;
use App\Models\Sale;
use Illuminate\Auth\Access\AuthorizationException;

class EnsurePortalInstallmentAccessAction
{
    /**
     * @throws AuthorizationException
     */
    public function execute(int $clientId, Installment $installment): Sale
    {
        $installment->loadMissing(['sale.buyers']);
        $sale = $installment->sale;

        if ($sale === null || $sale->status === Sale::STATUS_CANCELLED) {
            throw new AuthorizationException('Parcela não encontrada.');
        }

        $isOwner = $sale->client_id === $clientId
            || $sale->buyers->contains('id', $clientId);

        if (! $isOwner) {
            throw new AuthorizationException('Você não tem acesso a esta parcela.');
        }

        return $sale;
    }

    /**
     * @throws AuthorizationException
     */
    public function executeForSale(int $clientId, Sale $sale): void
    {
        if ($sale->status === Sale::STATUS_CANCELLED) {
            throw new AuthorizationException('Contrato não encontrado.');
        }

        $isOwner = $sale->client_id === $clientId
            || $sale->buyers()->where('clients.id', $clientId)->exists();

        if (! $isOwner) {
            throw new AuthorizationException('Você não tem acesso a este contrato.');
        }
    }
}
