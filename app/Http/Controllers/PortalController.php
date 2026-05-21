<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Portal\AuthenticatePortalAction;
use App\Actions\Portal\GetPortalDashboardAction;
use App\Http\Requests\PortalAccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PortalController extends Controller
{
    public function access(PortalAccessRequest $request, AuthenticatePortalAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->validated('cpf'),
            $request->validated('phone'),
        );

        return response()->json($result);
    }

    public function dashboard(Request $request, GetPortalDashboardAction $action): JsonResponse
    {
        $clientId = (int) $request->attributes->get('portal_client_id');

        return response()->json($action->execute($clientId));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('portal_token');

        if (is_string($token) && $token !== '') {
            Cache::forget("portal:token:{$token}");
        }

        return response()->json([
            'message' => 'Sessão encerrada.',
        ]);
    }
}
