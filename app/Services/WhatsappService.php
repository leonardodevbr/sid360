<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Installment;
use App\Models\InstallmentInteraction;
use App\Models\Setting;
use App\Support\DocumentHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public function send(string $phone, string $message): bool
    {
        $recipient = $this->formatRecipient($phone);

        if ($recipient === null) {
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
                    'phone' => $recipient,
                    'message' => $message,
                ]);

            if (! $response->successful()) {
                Log::warning('WhatsappService::send failed', [
                    'status' => $response->status(),
                    'phone' => $recipient,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsappService::send exception', [
                'message' => $e->getMessage(),
                'phone' => $recipient,
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
        $recipient = $this->formatRecipient($phone);

        if ($recipient === null) {
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
            'phone' => $recipient,
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
                    'phone' => $recipient,
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsappService::sendImage exception', [
                'message' => $e->getMessage(),
                'phone' => $recipient,
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

    public function sendDocument(
        string $phone,
        string $filename,
        ?string $caption = null,
        ?string $fileUrl = null,
        ?string $base64File = null,
        string $mimeType = 'application/pdf',
    ): bool {
        $recipient = $this->formatRecipient($phone);

        if ($recipient === null) {
            return false;
        }

        $fileUrl = $fileUrl !== null ? trim($fileUrl) : null;

        if ($fileUrl !== null && $fileUrl !== '') {
            if ($this->postSendFile($recipient, $filename, $caption, ['path' => $fileUrl])) {
                return true;
            }

            Log::warning('WhatsappService::sendDocument URL failed, trying base64 fallback', [
                'phone' => $recipient,
                'url' => $fileUrl,
            ]);
        }

        $base64 = $base64File;

        if ($base64 === null || trim($base64) === '') {
            $base64 = $fileUrl !== null ? $this->fetchUrlAsBase64($fileUrl) : null;
        }

        if ($base64 === null || trim($base64) === '') {
            return false;
        }

        return $this->postSendFile(
            $recipient,
            $filename,
            $caption,
            ['base64' => $this->normalizeBase64AsDataUri($base64, $mimeType)],
        );
    }

    /**
     * @param  array<string, string>  $filePayload
     */
    private function postSendFile(
        string $phoneOrJid,
        string $filename,
        ?string $caption,
        array $filePayload,
    ): bool {
        $config = $this->wppconnectConfig();

        if ($config === null) {
            return false;
        }

        $targets = array_values(array_unique(array_filter([
            $this->formatRecipientAsChatId($phoneOrJid),
            $this->formatRecipient($phoneOrJid),
        ])));

        foreach ($targets as $target) {
            $payload = array_merge([
                'phone' => $target,
                'filename' => $filename,
                'isGroup' => false,
            ], $filePayload);

            if ($caption !== null && $caption !== '') {
                $payload['caption'] = $caption;
            }

            if (isset($payload['base64'])) {
                if ($this->postWppconnect($config, 'send-file-base64', $payload)) {
                    return true;
                }
            }

            if ($this->postWppconnect($config, 'send-file', $payload)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{base_url: string, session: string, token: string}  $config
     */
    private function postWppconnect(array $config, string $endpoint, array $payload): bool
    {
        try {
            $response = Http::timeout((int) config('services.wppconnect.media_timeout', 90))
                ->withToken($config['token'])
                ->post("{$config['base_url']}/api/{$config['session']}/{$endpoint}", $payload);

            if (! $response->successful()) {
                Log::warning("WhatsappService::{$endpoint} failed", [
                    'status' => $response->status(),
                    'phone' => $payload['phone'] ?? null,
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("WhatsappService::{$endpoint} exception", [
                'message' => $e->getMessage(),
                'phone' => $payload['phone'] ?? null,
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
        string $filename,
        string $type,
        ?string $pdfUrl = null,
        ?string $pdfBase64 = null,
        int|string|null $installmentId = null,
        int|string|null $saleId = null,
        int|string|null $clientId = null,
        ?string $fileCaption = null,
        array $meta = [],
    ): array {
        $textSent = $this->send($phone, $message);

        $fileSent = null;
        $hasPdf = ($pdfUrl !== null && trim($pdfUrl) !== '')
            || ($pdfBase64 !== null && trim($pdfBase64) !== '');

        if ($hasPdf) {
            $fileSent = $this->sendDocument(
                phone: $phone,
                filename: $filename,
                caption: $fileCaption,
                fileUrl: $pdfUrl,
                base64File: $pdfBase64,
            );
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
                'has_pdf' => $hasPdf,
            ]),
        ]);

        return [
            'text_sent' => $textSent,
            'file_sent' => $fileSent,
        ];
    }

    public function fetchUrlAsBase64(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                Log::warning('WhatsappService::fetchUrlAsBase64 failed', [
                    'status' => $response->status(),
                    'url' => $url,
                ]);

                return null;
            }

            $body = $response->body();

            if ($body === '' || ! str_starts_with($body, '%PDF')) {
                Log::warning('WhatsappService::fetchUrlAsBase64 invalid pdf', [
                    'url' => $url,
                ]);

                return null;
            }

            return base64_encode($body);
        } catch (\Exception $e) {
            Log::error('WhatsappService::fetchUrlAsBase64 exception', [
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
     * @param  array<string, mixed>  $meta
     */
    public function notifySid(
        string $message,
        ?int $saleId = null,
        ?int $clientId = null,
        ?string $relatedClientPhone = null,
        string $type = InstallmentInteraction::TYPE_SID_NOTIFY,
        array $meta = [],
    ): bool {
        $sidPhone = $this->sidPhoneDigits();

        if ($relatedClientPhone !== null && DocumentHelper::phoneMatches($relatedClientPhone, $sidPhone)) {
            Log::warning('WhatsappService::notifySid skipped — same phone as client', [
                'sale_id' => $saleId,
                'client_id' => $clientId,
                'phone' => $sidPhone,
            ]);

            InstallmentInteraction::create([
                'sale_id' => $saleId,
                'client_id' => $clientId,
                'phone' => $sidPhone,
                'direction' => InstallmentInteraction::DIR_OUTBOUND,
                'type' => $type,
                'message' => $message,
                'meta' => array_merge($meta, [
                    'sent' => false,
                    'skipped' => 'same_phone_as_client',
                ]),
            ]);

            return false;
        }

        $sent = $this->send($sidPhone, $message);

        InstallmentInteraction::create([
            'sale_id' => $saleId,
            'client_id' => $clientId,
            'phone' => $sidPhone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => $type,
            'message' => $message,
            'meta' => array_merge($meta, ['sent' => $sent, 'recipient' => 'sid']),
        ]);

        if (! $sent) {
            Log::warning('WhatsappService::notifySid failed', [
                'sale_id' => $saleId,
                'client_id' => $clientId,
                'phone' => $sidPhone,
            ]);
        }

        return $sent;
    }

    public function sidPhoneDigits(): string
    {
        $digits = preg_replace('/\D/', '', (string) Setting::get('whatsapp_sid_phone', '5574988230151')) ?? '';

        return $digits !== '' ? $digits : '5574988230151';
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

    private function formatRecipient(string $phoneOrJid): ?string
    {
        $value = trim($phoneOrJid);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, '@')) {
            return $value;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) < 10) {
            return null;
        }

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            return $digits;
        }

        return strlen($digits) >= 11 ? "55{$digits}" : "559{$digits}";
    }

    private function formatRecipientAsChatId(string $phoneOrJid): ?string
    {
        $recipient = $this->formatRecipient($phoneOrJid);

        if ($recipient === null) {
            return null;
        }

        if (str_contains($recipient, '@')) {
            return $recipient;
        }

        return "{$recipient}@c.us";
    }

    private function normalizeBase64Payload(string $base64, string $mimeType): string
    {
        $base64 = preg_replace('/\s+/', '', trim($base64)) ?? '';

        if ($base64 === '') {
            return '';
        }

        if (str_starts_with($base64, 'data:')) {
            $pos = strpos($base64, ',');

            if ($pos !== false) {
                $base64 = substr($base64, $pos + 1);
            }
        }

        return $base64;
    }

    private function normalizeBase64AsDataUri(string $base64, string $mimeType): string
    {
        $base64 = preg_replace('/\s+/', '', trim($base64)) ?? '';

        if ($base64 === '') {
            return '';
        }

        if (str_starts_with($base64, 'data:')) {
            return $base64;
        }

        return "data:{$mimeType};base64,{$base64}";
    }
}
