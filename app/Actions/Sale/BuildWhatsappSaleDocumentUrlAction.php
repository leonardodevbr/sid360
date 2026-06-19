<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Installment;
use App\Models\Sale;
use Illuminate\Support\Facades\URL;

class BuildWhatsappSaleDocumentUrlAction
{
    public function contractUrl(Sale $sale, int $ttlMinutes = 20): string
    {
        return URL::temporarySignedRoute(
            'whatsapp.documents.sale.contract',
            now()->addMinutes($ttlMinutes),
            ['id' => $sale->id],
        );
    }

    public function carneUrl(Sale $sale, int $ttlMinutes = 20): string
    {
        return URL::temporarySignedRoute(
            'whatsapp.documents.sale.carne',
            now()->addMinutes($ttlMinutes),
            ['id' => $sale->id],
        );
    }

    public function reciboUrl(Installment $installment, int $ttlMinutes = 20): string
    {
        return URL::temporarySignedRoute(
            'whatsapp.documents.installment.recibo',
            now()->addMinutes($ttlMinutes),
            ['id' => $installment->id],
        );
    }
}
