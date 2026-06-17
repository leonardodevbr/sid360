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
    public function publicConfig(): JsonResponse
    {
        /** @var array<string, mixed> $loteamento */
        $loteamento = config('site.loteamento', []);

        $lat = (float) ($loteamento['lat'] ?? -11.4667);
        $lng = (float) ($loteamento['lng'] ?? -39.9833);

        return response()->json([
            'loteamento' => [
                'name' => (string) ($loteamento['name'] ?? ''),
                'address' => (string) ($loteamento['address'] ?? ''),
                'lat' => $lat,
                'lng' => $lng,
                'maps_embed_url' => (string) ($loteamento['maps_embed_url']
                    ?? 'https://maps.google.com/maps?q=' . $lat . ',' . $lng . '&hl=pt-BR&z=16&output=embed'),
            ],
            'whatsapp' => (string) config('site.whatsapp_phone', '5574988230151'),
        ]);
    }

    public function developments(): JsonResponse
    {
        $developments = Development::query()
            ->where('status', 'active')
            ->withCount([
                'lots',
                'lots as lots_available_count' => fn ($query) => $query->where('status', Lot::STATUS_AVAILABLE),
                'lots as lots_sold_count' => fn ($query) => $query->where('status', Lot::STATUS_SOLD),
            ])
            ->withMin('lots', 'total_value')
            ->withMax('lots', 'total_value')
            ->with(['media' => fn ($query) => $query->where('is_cover', true)->limit(1)])
            ->orderByDesc('is_featured')
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
        $development->loadAggregate('lots', 'min', 'total_value');
        $development->loadAggregate('lots', 'max', 'total_value');

        $lotModels = Lot::query()
            ->where('development_id', $development->id)
            ->with([
                'media' => fn ($query) => $query->where('is_cover', true)->limit(1),
                'zone',
                'street',
            ])
            ->orderBy('number')
            ->get();

        $lots = $lotModels->map(fn (Lot $lot): array => $this->lotResource($lot));

        return response()->json([
            'development' => $this->developmentResource($development),
            'lots' => $lots,
            'lot_groups' => $this->buildLotGroups($lotModels),
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
        $heroVideo = $development->heroVideo();

        return [
            'id' => $development->id,
            'name' => $development->name,
            'slug' => $development->id . '-' . Str::slug($development->name),
            'description' => $development->description,
            'location' => $development->location,
            'is_featured' => (bool) $development->is_featured,
            'cover_photo' => $development->media->first()?->url,
            'hero_video_url' => $heroVideo?->url,
            'hero_video_mime' => $heroVideo?->mime_type,
            'lots_count' => $development->lots_count ?? 0,
            'lots_available_count' => $development->lots_available_count ?? 0,
            'lots_sold_count' => $development->lots_sold_count ?? 0,
            'min_lot_value' => $development->lots_min_total_value !== null ? (int) $development->lots_min_total_value : null,
            'max_lot_value' => $development->lots_max_total_value !== null ? (int) $development->lots_max_total_value : null,
            'coordinates' => $development->coordinates,
            'map_center' => $development->map_center,
            'map_zoom' => $development->map_zoom,
            'down_payment_percent' => $development->down_payment_percent !== null
                ? (float) $development->down_payment_percent
                : 20,
            'photos' => $development->relationLoaded('media')
                ? $development->getRelation('media')->map(fn ($mediaItem): array => [
                    'id' => $mediaItem->id,
                    'url' => $mediaItem->url,
                    'type' => $mediaItem->type,
                    'caption' => $mediaItem->caption,
                ])->values()->all()
                : [],
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
            'size_label' => $lot->size_label,
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

    /**
     * @param  \Illuminate\Support\Collection<int, Lot>  $lots
     * @return list<array<string, mixed>>
     */
    private function buildLotGroups($lots): array
    {
        return $lots
            ->groupBy(fn (Lot $lot): string => $this->lotGroupKey($lot))
            ->map(function ($groupLots, string $key): array {
                /** @var \Illuminate\Support\Collection<int, Lot> $groupLots */
                $first = $groupLots->first();
                if ($first === null) {
                    return [];
                }

                $available = $groupLots->where('status', Lot::STATUS_AVAILABLE);
                $values = $groupLots
                    ->pluck('total_value')
                    ->filter(fn ($value): bool => $value !== null && (int) $value > 0)
                    ->map(fn ($value): int => (int) $value);

                $coverLot = $groupLots->first(
                    fn (Lot $lot): bool => $lot->coverPhoto() !== null || $lot->relationLoaded('media') && $lot->media->isNotEmpty(),
                );

                return [
                    'key' => $key,
                    'label' => $this->lotGroupLabel($first),
                    'area' => $this->lotEffectiveArea($first),
                    'available_count' => $available->count(),
                    'reserved_count' => $groupLots->where('status', Lot::STATUS_RESERVED)->count(),
                    'sold_count' => $groupLots->where('status', Lot::STATUS_SOLD)->count(),
                    'total_count' => $groupLots->count(),
                    'min_value' => $values->min() ?? 0,
                    'max_value' => $values->max() ?? 0,
                    'cover_photo' => $coverLot?->coverPhoto()?->url ?? $coverLot?->media->first()?->url,
                    'representative_lot_id' => $available->first()?->id ?? $first->id,
                    'lot_ids' => $groupLots->pluck('id')->values()->all(),
                ];
            })
            ->filter(fn (array $group): bool => $group !== [])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function lotGroupKey(Lot $lot): string
    {
        $label = trim((string) ($lot->size_label ?? ''));
        if ($label !== '') {
            return 'label:' . strtolower(preg_replace('/\s+/', '', $label) ?? $label);
        }

        $area = $this->lotEffectiveArea($lot);
        if ($area !== null) {
            return 'area:' . number_format($area, 2, '.', '');
        }

        return 'zone:' . ($lot->zone_id ?? 'none');
    }

    private function lotEffectiveArea(Lot $lot): ?float
    {
        if ($lot->area !== null) {
            return (float) $lot->area;
        }

        if ($lot->area_computed !== null) {
            return (float) $lot->area_computed;
        }

        return null;
    }

    private function lotGroupLabel(Lot $lot): string
    {
        $label = trim((string) ($lot->size_label ?? ''));
        if ($label !== '') {
            return str_replace(['x', 'X'], '×', $label);
        }

        $area = $this->lotEffectiveArea($lot);
        if ($area !== null) {
            return number_format($area, 0, ',', '.') . ' m²';
        }

        return $lot->zone?->name ?? 'Outros';
    }
}
