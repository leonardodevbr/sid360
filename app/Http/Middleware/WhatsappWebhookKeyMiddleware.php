<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\WppconnectConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsappWebhookKeyMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = WppconnectConfig::webhookKey();

        if ($expected === '') {
            return response()->json([
                'message' => 'Webhook não configurado.',
            ], 503);
        }

        $provided = $request->query('key');

        if (! is_string($provided) || $provided === '') {
            $header = $request->header('X-Whatsapp-Webhook-Key');
            $provided = is_string($header) ? $header : '';
        }

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Não autorizado.',
            ], 401);
        }

        return $next($request);
    }
}
