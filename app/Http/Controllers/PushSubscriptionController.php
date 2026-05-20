<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Notifications\TestPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Store or update a push subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:500',
            'keys.auth' => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth')
        );

        return response()->json(['message' => 'Inscrição de notificação registrada com sucesso.']);
    }

    /**
     * Envia uma notificação de teste para o usuário autenticado.
     * Útil para validar se Web Push (VAPID) está configurado e funcionando.
     */
    public function test(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if (!$user->pushSubscriptions()->exists()) {
            return response()->json([
                'message' => 'Nenhuma inscrição push encontrada. No app, permita notificações e aguarde a inscrição ser registrada.',
            ], 422);
        }

        $user->notify(new TestPushNotification);

        return response()->json([
            'message' => 'Notificação de teste enviada. Verifique o navegador (e a fila, se QUEUE_CONNECTION não for sync).',
        ]);
    }
}
