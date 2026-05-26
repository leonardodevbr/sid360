<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Development;
use App\Models\Lead;
use App\Models\Lot;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function developments(): JsonResponse
    {
        $developments = Development::query()
            ->where('status', 'active')
            ->whereHas('lots', fn ($query) => $query->where('status', Lot::STATUS_AVAILABLE))
            ->withCount([
                'lots',
                'lots as lots_available_count' => fn ($query) => $query->where('status', Lot::STATUS_AVAILABLE),
                'lots as lots_sold_count' => fn ($query) => $query->where('status', Lot::STATUS_SOLD),
            ])
            ->with(['media' => fn ($query) => $query->where('is_cover', true)->limit(1)])
            ->orderBy('name')
            ->get()
            ->map(fn (Development $development): array => $this->developmentResource($development));

        return response()->json($developments);
    }

    public function development(string $slug): JsonResponse
    {
        $development = $this->findActiveDevelopment($slug);

        $development->load(['media', 'zones', 'streets']);
        $development->loadCount([
            'lots',
            'lots as lots_available_count' => fn ($query) => $query->where('status', Lot::STATUS_AVAILABLE),
        ]);

        $lots = Lot::query()
            ->where('development_id', $development->id)
            ->with([
                'media' => fn ($query) => $query->where('is_cover', true)->limit(1),
                'zone',
                'street',
            ])
            ->orderBy('number')
            ->get()
            ->map(fn (Lot $lot): array => $this->lotResource($lot));

        return response()->json([
            'development' => $this->developmentResource($development),
            'lots' => $lots,
        ]);
    }

    public function lot(string|int $developmentId, string|int $lotId): JsonResponse
    {
        $lot = Lot::query()
            ->where('development_id', (int) $developmentId)
            ->with(['media', 'zone', 'street', 'development.media'])
            ->findOrFail((int) $lotId);

        return response()->json($this->lotResource($lot, true));
    }

    public function lotsAvailable(Request $request): JsonResponse
    {
        $query = Lot::query()
            ->where('status', Lot::STATUS_AVAILABLE)
            ->whereHas('development', fn ($developmentQuery) => $developmentQuery->where('status', 'active'))
            ->with([
                'development',
                'zone',
                'media' => fn ($mediaQuery) => $mediaQuery->where('is_cover', true)->limit(1),
            ]);

        if ($request->filled('development_id')) {
            $query->where('development_id', (int) $request->input('development_id'));
        }

        if ($request->filled('area_min')) {
            $query->where('area', '>=', (float) $request->input('area_min'));
        }

        if ($request->filled('area_max')) {
            $query->where('area', '<=', (float) $request->input('area_max'));
        }

        if ($request->filled('value_max')) {
            $query->where('total_value', '<=', (int) $request->input('value_max') * 100);
        }

        $lots = $query
            ->orderBy('total_value')
            ->paginate(18);

        return response()->json([
            'data' => collect($lots->items())->map(fn (Lot $lot): array => $this->lotResource($lot)),
            'meta' => [
                'total' => $lots->total(),
                'per_page' => $lots->perPage(),
                'current_page' => $lots->currentPage(),
                'last_page' => $lots->lastPage(),
            ],
        ]);
    }

    public function submitLead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lot_id' => ['required', 'integer', 'exists:lots,id'],
            'name' => ['required', 'string', 'max:200'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:200'],
            'address' => ['nullable', 'string', 'max:300'],
            'message' => ['nullable', 'string', 'max:1000'],
            'down_payment_percent' => ['nullable', 'string', 'max:10'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:360'],
            'simulated_installment_value' => ['nullable', 'integer'],
            'utm_source' => ['nullable', 'string', 'max:100'],
        ]);

        $lot = Lot::query()->with('development')->findOrFail((int) $data['lot_id']);

        if ($lot->status !== Lot::STATUS_AVAILABLE) {
            return response()->json(['error' => 'Este lote não está mais disponível.'], 422);
        }

        $lead = Lead::query()->create([
            ...$data,
            'development_id' => $lot->development_id,
            'status' => Lead::STATUS_PENDING,
            'utm_source' => $request->input('utm_source'),
        ]);

        $this->notifySid($lead, $lot);

        return response()->json(['ok' => true, 'lead_id' => $lead->id], 201);
    }

    private function findActiveDevelopment(string $slug): Development
    {
        $query = Development::query()->where('status', 'active');

        if (ctype_digit($slug)) {
            return $query->where('id', (int) $slug)->firstOrFail();
        }

        if (preg_match('/^(\d+)-/', $slug, $matches) === 1) {
            return $query->where('id', (int) $matches[1])->firstOrFail();
        }

        return $query
            ->whereRaw("LOWER(REPLACE(name, ' ', '-')) = ?", [strtolower($slug)])
            ->firstOrFail();
    }

    private function notifySid(Lead $lead, Lot $lot): void
    {
        try {
            $whatsapp = app(WhatsappService::class);
            $sidPhone = (string) Setting::get('whatsapp_sid_phone', '5574988230151');
            $formatMoney = fn (int $value): string => 'R$ ' . number_format($value / 100, 2, ',', '.');

            $lines = [
                '*Nova pré-reserva recebida!*',
                '',
                "*{$lead->name}*",
                $lead->phone,
            ];

            if ($lead->email) {
                $lines[] = $lead->email;
            }

            $lines[] = '';
            $lines[] = "Lote: *{$lot->number}* — {$lot->development?->name}";
            if ($lot->total_value) {
                $lines[] = 'Valor: *' . $formatMoney((int) $lot->total_value) . '*';
            }

            if ($lead->installments && $lead->simulated_installment_value) {
                $lines[] = sprintf(
                    'Simulação: %s%% entrada · %dx de %s',
                    $lead->down_payment_percent ?? '20',
                    $lead->installments,
                    $formatMoney((int) $lead->simulated_installment_value),
                );
            }

            if ($lead->message) {
                $lines[] = '';
                $lines[] = "\"{$lead->message}\"";
            }

            $lines[] = '';
            $lines[] = 'Acesse o sistema para ver e responder.';

            $whatsapp->send($sidPhone, implode("\n", $lines));
        } catch (\Exception) {
            // Lead saved; notification is best-effort.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function developmentResource(Development $development): array
    {
        return [
            'id' => $development->id,
            'name' => $development->name,
            'slug' => $development->id . '-' . Str::slug($development->name),
            'description' => $development->description,
            'location' => $development->location,
            'cover_photo' => $development->media->first()?->url,
            'lots_count' => $development->lots_count ?? 0,
            'lots_available_count' => $development->lots_available_count ?? 0,
            'lots_sold_count' => $development->lots_sold_count ?? 0,
            'coordinates' => $development->coordinates,
            'map_center' => $development->map_center,
            'map_zoom' => $development->map_zoom,
            'down_payment_percent' => $development->down_payment_percent !== null
                ? (float) $development->down_payment_percent
                : 20,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lotResource(Lot $lot, bool $full = false): array
    {
        $lot->loadMissing(['development', 'zone', 'street', 'media']);

        $base = [
            'id' => $lot->id,
            'number' => $lot->number,
            'block' => $lot->block,
            'area' => $lot->area !== null ? (float) $lot->area : null,
            'area_computed' => $lot->area_computed !== null ? (float) $lot->area_computed : null,
            'total_value' => (int) ($lot->total_value ?? 0),
            'status' => $lot->status,
            'coordinates' => $lot->coordinates,
            'cover_photo' => $lot->coverPhoto()?->url ?? $lot->media->first()?->url,
            'zone' => $lot->zone ? [
                'id' => $lot->zone->id,
                'name' => $lot->zone->name,
                'color' => $lot->zone->color,
            ] : null,
            'street' => $lot->street ? ['name' => $lot->street->name] : null,
            'full_address' => $lot->fullAddress(),
            'development' => $lot->development ? [
                'id' => $lot->development->id,
                'name' => $lot->development->name,
                'down_payment_percent' => $lot->development->down_payment_percent !== null
                    ? (float) $lot->development->down_payment_percent
                    : 20,
            ] : null,
            'down_payment_percent' => $lot->down_payment_percent !== null
                ? (float) $lot->down_payment_percent
                : ($lot->development?->down_payment_percent !== null
                    ? (float) $lot->development->down_payment_percent
                    : 20),
        ];

        if ($full) {
            $base['photos'] = $lot->media->map(fn ($mediaItem): array => [
                'id' => $mediaItem->id,
                'url' => $mediaItem->url,
                'type' => $mediaItem->type,
                'caption' => $mediaItem->caption,
            ])->values();
        }

        return $base;
    }
}
