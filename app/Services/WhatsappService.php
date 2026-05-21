<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InstallmentInteraction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    public function send(string $phone, string $message): bool
    {
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
                ->post("{$baseUrl}/api/{$session}/send-message", [
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
}
