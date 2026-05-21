<?php

declare(strict_types=1);

namespace App\Http\Middleware;

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
        // $expected = (string) config('services.wppconnect.webhook_key');
        $expected = '13d20efe60baa341cc8fcdfbb5ce0be69ca94894a8a37cde40d11325a8a7a97f';

        if ($expected === '') {
            return response()->json([
                'message' => 'Webhook não configurado.',
            ], 503);
        }

        $provided = $request->query('key');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Não autorizado.',
            ], 401);
        }

        return $next($request);
    }
}
