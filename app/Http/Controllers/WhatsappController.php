<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendWhatsappOtpRequest;
use App\Http\Requests\VerifyWhatsappOtpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappController extends Controller
{
    public function check(string $phone): JsonResponse
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 10) {
            return response()->json(['error' => 'Número inválido'], 422);
        }

        $numero = $this->formatBrazilNumber($digits);

        $baseUrl = rtrim((string) config('services.wppconnect.base_url'), '/');
        $session = (string) config('services.wppconnect.session');
        $token = (string) config('services.wppconnect.token');

        if ($token === '') {
            return response()->json(['error' => 'Serviço não configurado'], 503);
        }

        $url = "{$baseUrl}/api/{$session}/check-number-status/{$numero}";

        try {
            $response = Http::timeout(8)
                ->withToken($token)
                ->get($url);

            if (! $response->successful()) {
                Log::warning('WPPConnect check-number-status failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'number' => $numero,
                ]);

                $message = $response->status() === 401
                    ? 'Sessão ou token do WhatsApp inválido. Verifique o WPPConnect.'
                    : 'Falha ao verificar';

                return response()->json(['error' => $message], 503);
            }

            $data = $response->json();

            $hasWhatsapp =
                ($data['numberExists'] ?? false) === true
                || ($data['status'] ?? '') === 'has whatsapp'
                || ($data['response']['numberExists'] ?? false) === true;

            return response()->json([
                'has_whatsapp' => $hasWhatsapp,
                'number' => $numero,
            ]);
        } catch (\Exception $e) {
            Log::error('WPPConnect check-number-status exception', [
                'message' => $e->getMessage(),
                'number' => $numero,
            ]);

            return response()->json(['error' => 'Falha ao verificar'], 503);
        }
    }

    public function sendOtp(SendWhatsappOtpRequest $request): JsonResponse
    {
        $digits = preg_replace('/\D/', '', $request->input('phone', '')) ?? '';

        if (strlen($digits) < 10) {
            return response()->json(['error' => 'Número inválido'], 422);
        }

        $numero = $this->formatBrazilNumber($digits);
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $cacheKey = "whatsapp_otp_{$numero}";

        Cache::put($cacheKey, $code, now()->addMinutes(10));

        $baseUrl = rtrim((string) config('services.wppconnect.base_url'), '/');
        $session = (string) config('services.wppconnect.session');
        $token = $this->wppconnectMessageToken();

        if ($token === '') {
            return response()->json(['error' => 'Serviço não configurado'], 503);
        }

        $url = "{$baseUrl}/api/{$session}/send-message";

        try {
            $response = Http::timeout(8)
                ->withToken($token)
                ->post($url, [
                    'phone' => $numero,
                    'message' => "🔐 *Código de verificação Sid360*\n\n*{$code}*\n\nToque para copiar e cole no sistema.\nVálido por 10 minutos.",
                ]);

            if (! $response->successful()) {
                Log::warning('WPPConnect send-message failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'number' => $numero,
                ]);

                return response()->json(['error' => 'Falha ao enviar mensagem'], 503);
            }

            return response()->json(['ok' => true, 'number' => $numero]);
        } catch (\Exception $e) {
            Log::error('WPPConnect send-message exception', [
                'message' => $e->getMessage(),
                'number' => $numero,
            ]);

            return response()->json(['error' => 'Falha ao enviar mensagem'], 503);
        }
    }

    public function verifyOtp(VerifyWhatsappOtpRequest $request): JsonResponse
    {
        $digits = preg_replace('/\D/', '', $request->input('phone', '')) ?? '';
        $code = trim($request->input('code', ''));

        $numero = $this->formatBrazilNumber($digits);
        $cacheKey = "whatsapp_otp_{$numero}";
        $stored = Cache::get($cacheKey);

        if ($stored === null) {
            return response()->json(['valid' => false, 'error' => 'Código expirado'], 422);
        }

        if ($stored !== $code) {
            return response()->json(['valid' => false, 'error' => 'Código incorreto'], 422);
        }

        Cache::forget($cacheKey);

        return response()->json(['valid' => true]);
    }

    private function formatBrazilNumber(string $digits): string
    {
        return strlen($digits) >= 11 ? "55{$digits}" : "559{$digits}";
    }

    private function wppconnectMessageToken(): string
    {
        $tokenFull = (string) config('services.wppconnect.token');

        if ($tokenFull === '') {
            return '';
        }

        if (str_contains($tokenFull, ':')) {
            return substr($tokenFull, strpos($tokenFull, ':') + 1);
        }

        return $tokenFull;
    }
}
