<?php

declare(strict_types=1);

namespace App\Actions\Installment;

use App\Models\Installment;
use App\Services\EfiService;
use Throwable;

class GenerateInstallmentPixAction
{
    public function __construct(
        private readonly EfiService $efi,
        private readonly CalculateInstallmentChargeValueAction $calculateCharge,
    ) {}

    /**
     * @return array{
     *     txid: string,
     *     pix_copia_cola: string,
     *     qrcode: string,
     *     charge_value: float,
     *     charge_breakdown: array<string, mixed>
     * }
     *
     * @throws Throwable
     */
    public function execute(
        Installment $installment,
        bool $waivePenalties = false,
        ?int $expirySeconds = null,
    ): array {
        $installment->loadMissing(['sale.client']);

        if ($installment->status === Installment::STATUS_PAID) {
            throw new \InvalidArgumentException('Parcela já paga.');
        }

        $charge = $this->calculateCharge->execute($installment, $waivePenalties);
        $expiry = $expirySeconds ?? (int) config('services.efi.pix_expiry', 3600);
        $reference = 'Contrato '.str_pad((string) $installment->sale_id, 4, '0', STR_PAD_LEFT)
            .' – Parcela '.$installment->number;

        $pix = $this->efi->createPixCharge(
            valueInCents: (float) $charge['total_value'],
            debtorName: (string) $installment->sale->client->name,
            debtorCpf: (string) $installment->sale->client->cpf,
            reference: $reference,
            expirySeconds: $expiry,
        );

        $qrCode = $this->efi->getPixQrCode((int) $pix['loc_id']);
        $qrcode = $this->normalizePixQrCodeImage($qrCode['image']);
        $pixCopiaCola = $qrCode['copy_paste'] ?? $pix['pix_copia_cola'];

        $installment->update([
            'efi_txid' => $pix['txid'],
            'efi_pix_copia_cola' => $pixCopiaCola,
            'efi_pix_qrcode' => $qrcode,
            'efi_payment_type' => 'pix',
        ]);

        return [
            'txid' => $pix['txid'],
            'pix_copia_cola' => $pixCopiaCola,
            'qrcode' => $qrcode,
            'charge_value' => (float) $charge['total_value'],
            'charge_breakdown' => $charge,
        ];
    }

    private function normalizePixQrCodeImage(string $qrcode): string
    {
        if ($qrcode === '') {
            return '';
        }

        if (str_starts_with($qrcode, 'data:')) {
            $pos = strpos($qrcode, ',');

            if ($pos !== false) {
                return substr($qrcode, $pos + 1);
            }
        }

        return $qrcode;
    }
}
