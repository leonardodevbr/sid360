<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Installment\GenerateInstallmentBoletoAction;
use App\Actions\Installment\GenerateInstallmentPixAction;
use App\Actions\Portal\AuthenticatePortalAction;
use App\Actions\Portal\EnsurePortalInstallmentAccessAction;
use App\Actions\Portal\GetPortalDashboardAction;
use App\Http\Requests\PortalAccessRequest;
use App\Models\Installment;
use App\Support\PortalInstallmentMapper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

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

    public function generatePix(
        Request $request,
        string|int $installmentId,
        EnsurePortalInstallmentAccessAction $ensureAccess,
        GenerateInstallmentPixAction $generatePix,
    ): JsonResponse {
        $clientId = (int) $request->attributes->get('portal_client_id');
        $installment = Installment::query()->findOrFail((int) $installmentId);

        try {
            $sale = $ensureAccess->execute($clientId, $installment);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        if ($installment->status === Installment::STATUS_PAID) {
            return response()->json(['error' => 'Parcela já paga.'], 422);
        }

        try {
            $result = $generatePix->execute(
                installment: $installment,
                waivePenalties: false,
            );

            $installment->refresh();

            return response()->json([
                'txid' => $result['txid'],
                'pix_copia_cola' => $result['pix_copia_cola'],
                'qrcode' => $result['qrcode'],
                'charge_value' => $result['charge_value'],
                'charge_breakdown' => $result['charge_breakdown'],
                'installment' => PortalInstallmentMapper::toArray($installment, $sale),
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar PIX: '.$e->getMessage()], 500);
        }
    }

    public function generateBoleto(
        Request $request,
        string|int $installmentId,
        EnsurePortalInstallmentAccessAction $ensureAccess,
        GenerateInstallmentBoletoAction $generateBoleto,
    ): JsonResponse {
        $clientId = (int) $request->attributes->get('portal_client_id');
        $installment = Installment::query()->findOrFail((int) $installmentId);

        try {
            $sale = $ensureAccess->execute($clientId, $installment);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }

        if ($installment->status === Installment::STATUS_PAID) {
            return response()->json(['error' => 'Parcela já paga.'], 422);
        }

        try {
            $result = $generateBoleto->execute(
                installment: $installment,
                waivePenalties: false,
            );

            $installment->refresh();

            return response()->json([
                'charge_id' => $result['charge_id'],
                'barcode' => $result['barcode'],
                'pdf' => $result['pdf'],
                'link' => $result['link'],
                'due_date' => $result['due_date'],
                'charge_value' => $result['charge_value'],
                'charge_breakdown' => $result['charge_breakdown'],
                'installment' => PortalInstallmentMapper::toArray($installment, $sale),
            ]);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Erro ao gerar boleto: '.$e->getMessage()], 500);
        }
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
