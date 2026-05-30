<?php

declare(strict_types=1);

namespace App\Services;

use Efi\EfiPay;
use Efi\Exception\EfiException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EfiService
{
    /**
     * @return array<string, mixed>
     */
    private function pixClientOptions(): array
    {
        return [
            'client_id' => (string) config('services.efi.client_id'),
            'client_secret' => (string) config('services.efi.client_secret'),
            'sandbox' => (bool) config('services.efi.sandbox', true),
            'certificate' => $this->resolveCertificatePath(),
            'pwdCertificate' => (string) config('services.efi.certificate_password', ''),
        ];
    }

    private function pixClient(): EfiPay
    {
        return new EfiPay($this->pixClientOptions());
    }

    private function cobrancasClient(): EfiPay
    {
        return new EfiPay([
            'client_id' => (string) config('services.efi.client_id'),
            'client_secret' => (string) config('services.efi.client_secret'),
            'sandbox' => (bool) config('services.efi.sandbox', true),
        ]);
    }

    private function resolveCertificatePath(): string
    {
        $path = trim((string) config('services.efi.certificate', ''));

        if ($path === '') {
            throw new RuntimeException(
                'Certificado PIX não configurado. Defina EFI_CERTIFICATE_PATH no arquivo .env.',
            );
        }

        $candidates = [$path];

        if (! str_starts_with($path, '/')) {
            $candidates[] = base_path($path);
            $candidates[] = storage_path($path);
            $candidates[] = storage_path('app/'.$path);
            $candidates[] = storage_path('app/private/'.$path);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        throw new RuntimeException(
            'Arquivo de certificado PIX não encontrado. Verifique EFI_CERTIFICATE_PATH no .env: '.$path,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createPixCharge(
        float $valueInCents,
        string $debtorName,
        string $debtorCpf,
        string $reference,
        int $expirySeconds = 3600,
    ): array {
        $pixKey = trim((string) config('services.efi.pix_key'));

        if ($pixKey === '') {
            throw new RuntimeException(
                'Chave PIX não configurada. Defina EFI_PIX_KEY no arquivo .env.',
            );
        }

        $body = [
            'calendario' => ['expiracao' => $expirySeconds],
            'devedor' => [
                'cpf' => preg_replace('/\D/', '', $debtorCpf),
                'nome' => $debtorName,
            ],
            'valor' => ['original' => number_format($valueInCents / 100, 2, '.', '')],
            'chave' => $pixKey,
            'solicitacaoPagador' => $reference,
        ];

        try {
            $response = $this->pixClient()->pixCreateImmediateCharge([], $body);

            return [
                'txid' => $response['txid'],
                'loc_id' => $response['loc']['id'],
                'location' => $response['location'],
                'pix_copia_cola' => $response['pixCopiaECola'] ?? null,
                'status' => $response['status'],
            ];
        } catch (EfiException $e) {
            Log::error('EfiService::createPixCharge', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @return array{image: string, copy_paste: string|null}
     */
    public function getPixQrCode(int $locId): array
    {
        try {
            $response = $this->pixClient()->pixGenerateQRCode(['id' => $locId]);

            return [
                'image' => (string) ($response['imagemQrcode'] ?? ''),
                'copy_paste' => isset($response['qrcode']) ? (string) $response['qrcode'] : null,
            ];
        } catch (EfiException $e) {
            Log::error('EfiService::getPixQrCode', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function createBoleto(
        float $valueInCents,
        string $debtorName,
        string $debtorCpf,
        string $dueDate,
        string $description,
        ?string $debtorPhone = null,
        bool $waivePenalties = false,
    ): array {
        $customer = [
            'name' => $debtorName,
            'cpf' => preg_replace('/\D/', '', $debtorCpf),
        ];

        if ($debtorPhone) {
            $customer['phone_number'] = preg_replace('/\D/', '', $debtorPhone);
        }

        $body = [
            'items' => [[
                'name' => $description,
                'value' => (int) $valueInCents,
                'amount' => 1,
            ]],
            'payment' => [
                'banking_billet' => [
                    'expire_at' => $dueDate,
                    'customer' => $customer,
                    'message' => mb_substr($description, 0, 80),
                    'configurations' => $waivePenalties
                        ? ['fine' => 0, 'interest' => 0]
                        : ['fine' => 200, 'interest' => 33],
                ],
            ],
        ];

        try {
            Log::info('EfiService::createBoleto request', [
                'customer_cpf' => $this->maskDocument($customer['cpf']),
                'customer_name' => $debtorName,
                'value_cents' => (int) $valueInCents,
                'sandbox' => (bool) config('services.efi.sandbox', true),
            ]);

            $response = $this->cobrancasClient()->createOneStepCharge([], $body);
            $data = $response['data'];

            return [
                'charge_id' => $data['charge_id'],
                'barcode' => $data['barcode'],
                'link' => $data['link'],
                'pdf' => $data['pdf']['charge'] ?? null,
                'status' => $data['status'],
            ];
        } catch (EfiException $e) {
            Log::error('EfiService::createBoleto', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function createCarne(
        float $installmentValueCents,
        int $installmentsCount,
        string $firstDueDate,
        string $debtorName,
        string $debtorCpf,
        string $itemDescription,
        ?string $debtorPhone = null,
        ?string $message = null,
    ): array {
        $customer = [
            'name' => $debtorName,
            'cpf' => preg_replace('/\D/', '', $debtorCpf),
        ];

        if ($debtorPhone) {
            $customer['phone_number'] = preg_replace('/\D/', '', $debtorPhone);
        }

        $body = [
            'items' => [[
                'name' => $itemDescription,
                'value' => (int) $installmentValueCents,
                'amount' => 1,
            ]],
            'customer' => $customer,
            'expire_at' => $firstDueDate,
            'repeats' => $installmentsCount,
            'split_items' => false,
            'configurations' => [
                'fine' => 250,
                'interest' => 33,
            ],
        ];

        if ($message) {
            $body['message'] = mb_substr($message, 0, 80);
        }

        try {
            Log::info('EfiService::createCarne request', [
                'customer_cpf' => $this->maskDocument($customer['cpf']),
                'customer_name' => $debtorName,
                'repeats' => $installmentsCount,
                'sandbox' => (bool) config('services.efi.sandbox', true),
            ]);

            $response = $this->cobrancasClient()->createCarnet([], $body);
            $data = $response['data'];

            return [
                'carnet_id' => $data['carnet_id'],
                'status' => $data['status'],
                'link' => $data['link'],
                'pdf_carnet' => $data['pdf']['carnet'] ?? null,
                'pdf_cover' => $data['pdf']['cover'] ?? null,
                'charges' => collect($data['charges'])->map(fn (array $charge): array => [
                    'charge_id' => $charge['charge_id'],
                    'parcel' => (int) $charge['parcel'],
                    'status' => $charge['status'],
                    'value' => (int) $charge['value'],
                    'expire_at' => $charge['expire_at'],
                    'pdf' => $charge['pdf']['charge'] ?? null,
                    'barcode' => $charge['barcode'] ?? null,
                ])->toArray(),
            ];
        } catch (EfiException $e) {
            Log::error('EfiService::createCarne', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function maskDocument(string $digits): string
    {
        if (strlen($digits) < 4) {
            return '****';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPixCharge(string $txid): array
    {
        try {
            return $this->pixClient()->pixDetailCharge(['txid' => $txid]);
        } catch (EfiException $e) {
            Log::error('EfiService::getPixCharge', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getCobrancaNotification(string $token): array
    {
        try {
            return $this->cobrancasClient()->getNotification(['token' => $token]);
        } catch (EfiException $e) {
            Log::error('EfiService::getCobrancaNotification', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
