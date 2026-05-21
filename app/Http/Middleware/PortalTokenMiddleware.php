<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PortalTokenMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-Portal-Token');

        if (! is_string($token) || $token === '') {
            return response()->json([
                'message' => 'Acesso não autorizado.',
            ], 401);
        }

        $clientId = Cache::get("portal:token:{$token}");

        if ($clientId === null) {
            return response()->json([
                'message' => 'Sessão expirada. Faça login novamente.',
            ], 401);
        }

        $request->attributes->set('portal_client_id', (int) $clientId);
        $request->attributes->set('portal_token', $token);

        return $next($request);
    }
}
