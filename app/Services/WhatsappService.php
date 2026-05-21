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
        ?int $installmentId = null,
        ?int $saleId = null,
        ?int $clientId = null,
        array $meta = []
    ): bool {
        $sent = $this->send($phone, $message);

        InstallmentInteraction::create([
            'installment_id' => $installmentId,
            'sale_id' => $saleId,
            'client_id' => $clientId,
            'phone' => $phone,
            'direction' => InstallmentInteraction::DIR_OUTBOUND,
            'type' => $type,
            'message' => $message,
            'meta' => array_merge($meta, ['sent' => $sent]),
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
