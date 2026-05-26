<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Lot;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('sales.view');

        $leads = Lead::query()
            ->with(['lot.development', 'lot.zone', 'development'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('development_id'), fn ($query) => $query->where('development_id', (int) $request->input('development_id')))
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => collect($leads->items())->map(fn (Lead $lead): array => $this->resource($lead)),
            'meta' => [
                'total' => $leads->total(),
                'pending' => Lead::query()->where('status', Lead::STATUS_PENDING)->count(),
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
            ],
        ]);
    }

    public function show(string|int $id): JsonResponse
    {
        $this->authorize('sales.view');

        $lead = Lead::query()
            ->with(['lot.development', 'lot.zone', 'development'])
            ->findOrFail((int) $id);

        return response()->json($this->resource($lead));
    }

    public function updateStatus(Request $request, string|int $id): JsonResponse
    {
        $this->authorize('sales.edit');

        $lead = Lead::query()
            ->with(['lot', 'development'])
            ->findOrFail((int) $id);

        $data = $request->validate([
            'status' => ['required', 'in:pending,contacted,converted,rejected'],
        ]);

        $lead->update(['status' => $data['status']]);

        if ($data['status'] === Lead::STATUS_CONTACTED && $lead->phone) {
            try {
                $whatsapp = app(WhatsappService::class);
                $developmentName = $lead->development?->name ?? $lead->lot?->development?->name ?? 'empreendimento';
                $lotNumber = $lead->lot?->number ?? '–';

                $message = implode("\n", array_filter([
                    "Olá, *{$lead->name}*!",
                    '',
                    "Recebemos seu interesse no lote *{$lotNumber}* do {$developmentName}.",
                    '',
                    'Entrarei em contato em breve para conversarmos!',
                    '',
                    '_Sid360 Imóveis_',
                    '(74) 9 8823-0151',
                ]));

                $whatsapp->send($lead->phone, $message);
            } catch (\Exception) {
                // Lead updated; notification is best-effort.
            }
        }

        return response()->json(['ok' => true, 'status' => $lead->status]);
    }

    public function convertToSale(string|int $id): JsonResponse
    {
        $this->authorize('sales.create');

        $lead = Lead::query()->with(['lot'])->findOrFail((int) $id);

        if ($lead->lot?->status !== Lot::STATUS_AVAILABLE) {
            return response()->json(['error' => 'Lote não está mais disponível.'], 422);
        }

        $lead->update(['status' => Lead::STATUS_CONVERTED]);

        return response()->json([
            'prefill' => [
                'lot_id' => $lead->lot_id,
                'name' => $lead->name,
                'cpf' => $lead->cpf,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'down_payment_percent' => $lead->down_payment_percent,
                'installments' => $lead->installments,
            ],
        ]);
    }

    public function destroy(string|int $id): JsonResponse
    {
        $this->authorize('sales.delete');

        Lead::query()->findOrFail((int) $id)->delete();

        return response()->json(['message' => 'Lead excluído.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(Lead $lead): array
    {
        $formatMoney = fn (?int $value): ?string => $value
            ? 'R$ ' . number_format($value / 100, 2, ',', '.')
            : null;

        $development = $lead->development ?? $lead->lot?->development;

        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'cpf' => $lead->cpf,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'message' => $lead->message,
            'status' => $lead->status,
            'down_payment_percent' => $lead->down_payment_percent,
            'installments' => $lead->installments,
            'simulated_installment_value' => $formatMoney($lead->simulated_installment_value),
            'lot' => $lead->lot ? [
                'id' => $lead->lot->id,
                'number' => $lead->lot->number,
                'area' => $lead->lot->area,
                'value' => $formatMoney((int) ($lead->lot->total_value ?? 0)),
                'zone' => $lead->lot->zone?->name,
            ] : null,
            'development' => $development ? [
                'id' => $development->id,
                'name' => $development->name,
            ] : null,
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }
}
