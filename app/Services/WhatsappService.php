<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Installment;
use App\Models\InstallmentInteraction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public function send(string $phone, string $message): bool
    {
        $numero = $this->formatPhoneNumber($phone);

        if ($numero === null) {
            return false;
        }

        $config = $this->wppconnectConfig();

        if ($config === null) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('services.wppconnect.timeout', 30))
                ->withToken($config['token'])
                ->post("{$config['base_url']}/api/{$config['session']}/send-message", [
                    'phone' => $numero,
                    'message' => $message,
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsappService::send failed', [
                    'status' => $response->status(),
                    'phone' => $numero,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsappService::send exception', [
                'message' => $e->getMessage(),
                'phone' => $numero,
            ]);

            return false;
        }
    }

    public function sendImage(
        string $phone,
        string $base64Image,
        ?string $caption = null,
        string $filename = 'pix-qrcode.png',
    ): bool {
        $numero = $this->formatPhoneNumber($phone);

        if ($numero === null) {
            return false;
        }

        $config = $this->wppconnectConfig();

        if ($config === null) {
            return false;
        }

        $base64 = $this->normalizeBase64Payload($base64Image, 'image/png');

        if ($base64 === '') {
            return false;
        }

        $payload = [
            'phone' => $numero,
            'base64' => $base64,
            'filename' => $filename,
            'isGroup' => false,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        try {
            $response = Http::timeout((int) config('services.wppconnect.media_timeout', 90))
                ->withToken($config['token'])
                ->post("{$config['base_url']}/api/{$config['session']}/send-image", $payload);

            if (! $response->successful()) {
                Log::warning('WhatsappService::sendImage failed', [
                    'status' => $response->status(),
                    'phone' => $numero,
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsappService::sendImage exception', [
                'message' => $e->getMessage(),
                'phone' => $numero,
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function sendPixAndRecord(
        string $phone,
        string $message,
        ?string $qrCodeImage,
        string $type,
        int|string|null $installmentId = null,
        int|string|null $saleId = null,
        int|string|null $clientId = null,
        ?string $imageCaption = null,
        array $meta = [],
    ): bool {
        $textSent = $this->send($phone, $message);

        $imageSent = null;

        if ($qrCodeImage !== null && trim($qrCodeImage) !== '') {
            $imageSent = $this->sendImage($phone, $qrCodeImage, $imageCaption);
        }

        InstallmentInteraction::create([
            'installment_id' => $installmentId !== null ? (int) $installmentId : null,
            'sale_id' => $saleId !== null ? (int) $saleId : null,
            'client_id' => $clientId !== null ? (int) $clientId : null,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => $type,
            'message' => $message,
            'meta' => array_merge($meta, [
                'sent' => $textSent,
                'text_sent' => $textSent,
                'image_sent' => $imageSent,
                'has_qrcode' => $qrCodeImage !== null && trim($qrCodeImage) !== '',
            ]),
        ]);

        return $textSent;
    }

    public function sendDocumentBase64(
        string $phone,
        string $base64File,
        string $filename,
        ?string $caption = null,
        string $mimeType = 'application/pdf',
    ): bool {
        $numero = $this->formatPhoneNumber($phone);

        if ($numero === null) {
            return false;
        }

        $config = $this->wppconnectConfig();

        if ($config === null) {
            return false;
        }

        $base64 = $this->normalizeBase64Payload($base64File, $mimeType);

        if ($base64 === '') {
            return false;
        }

        $payload = [
            'phone' => $numero,
            'base64' => $base64,
            'filename' => $filename,
            'isGroup' => false,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        try {
            $response = Http::timeout((int) config('services.wppconnect.media_timeout', 90))
                ->withToken($config['token'])
                ->post("{$config['base_url']}/api/{$config['session']}/send-file", $payload);

            if (! $response->successful()) {
                Log::warning('WhatsappService::sendDocumentBase64 failed', [
                    'status' => $response->status(),
                    'phone' => $numero,
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsappService::sendDocumentBase64 exception', [
                'message' => $e->getMessage(),
                'phone' => $numero,
            ]);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{text_sent: bool, file_sent: bool|null}
     */
    public function sendBoletoAndRecord(
        string $phone,
        string $message,
        ?string $pdfBase64,
        string $filename,
        string $type,
        int|string|null $installmentId = null,
        int|string|null $saleId = null,
        int|string|null $clientId = null,
        ?string $fileCaption = null,
        array $meta = [],
    ): array {
        $textSent = $this->send($phone, $message);

        $fileSent = null;

        if ($pdfBase64 !== null && trim($pdfBase64) !== '') {
            $fileSent = $this->sendDocumentBase64($phone, $pdfBase64, $filename, $fileCaption);
        }

        InstallmentInteraction::create([
            'installment_id' => $installmentId !== null ? (int) $installmentId : null,
            'sale_id' => $saleId !== null ? (int) $saleId : null,
            'client_id' => $clientId !== null ? (int) $clientId : null,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => $type,
            'message' => $message,
            'meta' => array_merge($meta, [
                'sent' => $textSent,
                'text_sent' => $textSent,
                'file_sent' => $fileSent,
                'has_pdf' => $pdfBase64 !== null && trim($pdfBase64) !== '',
            ]),
        ]);

        return [
            'text_sent' => $textSent,
            'file_sent' => $fileSent,
        ];
    }

    public function fetchUrlAsBase64DataUri(string $url, string $mimeType = 'application/pdf'): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                Log::warning('WhatsappService::fetchUrlAsBase64DataUri failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return null;
            }

            $body = $response->body();

            if ($body === '') {
                return null;
            }

            return 'data:'.$mimeType.';base64,'.base64_encode($body);
        } catch (\Exception $e) {
            Log::error('WhatsappService::fetchUrlAsBase64DataUri exception', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function sendAndRecord(
        string $phone,
        string $message,
        string $type,
        int|string|null $installmentId = null,
        int|string|null $saleId = null,
        int|string|null $clientId = null,
        array $meta = []
    ): bool {
        $sent = $this->send($phone, $message);

        InstallmentInteraction::create([
            'installment_id' => $installmentId !== null ? (int) $installmentId : null,
            'sale_id' => $saleId !== null ? (int) $saleId : null,
            'client_id' => $clientId !== null ? (int) $clientId : null,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => $type,
            'message' => $message,
            'meta' => array_merge($meta, ['sent' => $sent]),
        ]);

        return $sent;
    }

    /**
     * @param  array<int, array{title: string, rows: list<array{rowId: string, title: string, description: string}>}>  $sections
     */
    public function sendList(
        string $phone,
        string $description,
        string $buttonText,
        array $sections
    ): bool {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) < 10) {
            return false;
        }

        $numero = strlen($digits) >= 11 ? "55{$digits}" : "559{$digits}";
        $baseUrl = rtrim((string) config('services.wppconnect.base_url'), '/');
        $session = (string) config('services.wppconnect.session');
        $tokenFull = (string) config('services.wppconnect.token');

        if ($tokenFull === '') {
            return false;
        }

        $token = str_contains($tokenFull, ':')
            ? substr($tokenFull, strpos($tokenFull, ':') + 1)
            : $tokenFull;

        try {
            $response = Http::timeout(10)
                ->withToken($token)
                ->post("{$baseUrl}/api/{$session}/send-list-message", [
                    'phone' => ["{$numero}@c.us"],
                    'isGroup' => false,
                    'description' => $description,
                    'buttonText' => $buttonText,
                    'sections' => $sections,
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsappService::sendList failed', [
                    'status' => $response->status(),
                    'phone' => $numero,
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsappService::sendList exception', [
                'message' => $e->getMessage(),
                'phone' => $numero,
            ]);

            return false;
        }
    }

    /**
     * @param  array<int, array{title: string, rows: list<array{rowId: string, title: string, description: string}>}>  $sections
     * @param  array<string, mixed>  $meta
     */
    public function sendListAndRecord(
        string $phone,
        string $description,
        string $buttonText,
        array $sections,
        string $type,
        int|string|null $installmentId = null,
        int|string|null $saleId = null,
        int|string|null $clientId = null,
        array $meta = []
    ): bool {
        $sent = $this->sendList($phone, $description, $buttonText, $sections);

        InstallmentInteraction::create([
            'installment_id' => $installmentId !== null ? (int) $installmentId : null,
            'sale_id' => $saleId !== null ? (int) $saleId : null,
            'client_id' => $clientId !== null ? (int) $clientId : null,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => $type,
            'message' => $description,
            'meta' => array_merge($meta, ['sent' => $sent, 'format' => 'list']),
        ]);

        return $sent;
    }

    /**
     * Interpola variáveis {nome}, {contrato}, etc. numa mensagem template.
     *
     * @param  array<string, string>  $vars
     */
    public function interpolate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }

    public function buildPixPaymentMessage(
        string $clientName,
        string $contractNo,
        Installment $installment,
        string $pixCopyPaste,
    ): string {
        $parcela = $installment->type === Installment::TYPE_DOWN_PAYMENT
            ? 'Entrada'
            : "Parcela {$installment->number}";

        $dueDate = $installment->due_date?->format('d/m/Y') ?? '—';
        $value = 'R$ '.number_format((int) $installment->value / 100, 2, ',', '.');

        return implode("\n", [
            "Olá, *{$clientName}*!",
            '',
            "Segue o PIX da *{$parcela}* do contrato *{$contractNo}*:",
            '',
            "Vencimento: *{$dueDate}*",
            "Valor: *{$value}*",
            '',
            '*Código PIX (Copia e Cola):*',
            $pixCopyPaste,
            '',
            'Qualquer dúvida, estou à disposição.',
            '_Sid360 Imóveis_',
        ]);
    }

    public function buildPixImageCaption(
        string $contractNo,
        Installment $installment,
    ): string {
        $parcela = $installment->type === Installment::TYPE_DOWN_PAYMENT
            ? 'Entrada'
            : "Parcela {$installment->number}";

        $dueDate = $installment->due_date?->format('d/m/Y') ?? '—';
        $value = 'R$ '.number_format((int) $installment->value / 100, 2, ',', '.');

        return implode("\n", [
            "QR Code PIX — {$parcela}",
            "Contrato {$contractNo}",
            "Valor: {$value}",
            "Vencimento: {$dueDate}",
        ]);
    }

    public function buildBoletoPaymentMessage(
        string $clientName,
        string $contractNo,
        Installment $installment,
        ?string $barcode = null,
        ?string $pdfUrl = null,
    ): string {
        $parcela = $installment->type === Installment::TYPE_DOWN_PAYMENT
            ? 'Entrada'
            : "Parcela {$installment->number}";

        $dueDate = $installment->due_date?->format('d/m/Y') ?? '—';
        $value = 'R$ '.number_format((int) $installment->value / 100, 2, ',', '.');

        $lines = [
            "Olá, *{$clientName}*!",
            '',
            "Segue o boleto da *{$parcela}* do contrato *{$contractNo}*:",
            '',
            "Vencimento: *{$dueDate}*",
            "Valor: *{$value}*",
        ];

        if ($barcode !== null && trim($barcode) !== '') {
            $lines[] = '';
            $lines[] = '*Linha digitável:*';
            $lines[] = trim($barcode);
        }

        if ($pdfUrl !== null && trim($pdfUrl) !== '') {
            $lines[] = '';
            $lines[] = '*Link do boleto:*';
            $lines[] = trim($pdfUrl);
        }

        $lines[] = '';
        $lines[] = 'Qualquer dúvida, estou à disposição.';
        $lines[] = '_Sid360 Imóveis_';

        return implode("\n", $lines);
    }

    public function buildBoletoFileCaption(
        string $contractNo,
        Installment $installment,
    ): string {
        $parcela = $installment->type === Installment::TYPE_DOWN_PAYMENT
            ? 'Entrada'
            : "Parcela {$installment->number}";

        $dueDate = $installment->due_date?->format('d/m/Y') ?? '—';
        $value = 'R$ '.number_format((int) $installment->value / 100, 2, ',', '.');

        return implode("\n", [
            "Boleto — {$parcela}",
            "Contrato {$contractNo}",
            "Valor: {$value}",
            "Vencimento: {$dueDate}",
        ]);
    }

    /**
     * @return array{base_url: string, session: string, token: string}|null
     */
    private function wppconnectConfig(): ?array
    {
        $baseUrl = rtrim((string) config('services.wppconnect.base_url'), '/');
        $session = (string) config('services.wppconnect.session');
        $tokenFull = (string) config('services.wppconnect.token');

        if ($tokenFull === '' || $baseUrl === '' || $session === '') {
            return null;
        }

        $token = str_contains($tokenFull, ':')
            ? substr($tokenFull, strpos($tokenFull, ':') + 1)
            : $tokenFull;

        return [
            'base_url' => $baseUrl,
            'session' => $session,
            'token' => $token,
        ];
    }

    private function formatPhoneNumber(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 10) {
            return null;
        }

        return strlen($digits) >= 11 ? "55{$digits}" : "559{$digits}";
    }

    private function normalizeBase64Payload(string $base64, string $mimeType): string
    {
        $base64 = trim($base64);

        if ($base64 === '') {
            return '';
        }

        if (str_starts_with($base64, 'data:')) {
            return $base64;
        }

        return "data:{$mimeType};base64,{$base64}";
    }
}
