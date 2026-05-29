<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Installment;
use App\Models\Sale;
use Carbon\Carbon;

final class PortalInstallmentMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Installment $installment, Sale $sale, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $status = $installment->status;

        if ($status === Installment::STATUS_PENDING && $installment->due_date?->lt($today)) {
            $status = Installment::STATUS_OVERDUE;
        }

        return [
            'id' => $installment->id,
            'type' => $installment->type,
            'number' => $installment->number,
            'due_date' => $installment->due_date?->toDateString(),
            'value' => (int) $installment->value,
            'paid_at' => $installment->paid_at?->toDateString(),
            'status' => $status,
            'sale_id' => $sale->id,
            'contract_no' => self::contractNumber($sale),
            'efi_charge_id' => $installment->efi_charge_id,
            'efi_txid' => $installment->efi_txid,
            'efi_barcode' => $installment->efi_barcode,
            'efi_pdf_url' => $installment->efi_pdf_url,
            'efi_pix_copia_cola' => $installment->efi_pix_copia_cola,
            'efi_pix_qrcode' => $installment->efi_pix_qrcode,
            'efi_payment_type' => $installment->efi_payment_type,
        ];
    }

    public static function contractNumber(Sale $sale): string
    {
        return str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT).'/'.$sale->sale_date?->format('Y');
    }
}
