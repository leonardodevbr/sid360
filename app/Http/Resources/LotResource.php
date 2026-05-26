<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'development_id' => $this->development_id,
            'development' => $this->whenLoaded('development', fn () => new DevelopmentResource($this->development)),
            'zone_id' => $this->zone_id,
            'zone' => $this->whenLoaded('zone', fn () => [
                'id' => $this->zone->id,
                'name' => $this->zone->name,
                'type' => $this->zone->type,
                'color' => $this->zone->color,
                'parent' => $this->zone->parent ? [
                    'id' => $this->zone->parent->id,
                    'name' => $this->zone->parent->name,
                ] : null,
            ]),
            'street_id' => $this->street_id,
            'street' => $this->whenLoaded('street', fn () => [
                'id' => $this->street->id,
                'name' => $this->street->name,
            ]),
            'full_address' => $this->fullAddress(),
            'number' => $this->number,
            'block' => $this->block,
            'area' => $this->area !== null ? (float) $this->area : null,
            'area_computed' => $this->area_computed !== null ? (float) $this->area_computed : null,
            'coordinates' => $this->normalizedCoordinates(),
            'total_value' => $this->total_value !== null ? (int) $this->total_value : null,
            'down_payment_percent' => $this->down_payment_percent !== null
                ? (float) $this->down_payment_percent
                : null,
            'effective_down_payment_percent' => $this->effectiveDownPaymentPercent(),
            'uses_development_payment_terms' => $this->down_payment_percent === null,
            'status' => $this->status,
            'cover_photo' => $this->coverPhoto()?->url,
            'photos_count' => $this->media()->where('type', 'photo')->count(),
            'media' => $this->whenLoaded('media', fn () =>
                $this->media->map(fn ($mediaItem) => [
                    'id' => $mediaItem->id,
                    'url' => $mediaItem->url,
                    'type' => $mediaItem->type,
                    'caption' => $mediaItem->caption,
                    'is_cover' => $mediaItem->is_cover,
                    'order' => $mediaItem->order,
                ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<list<float>>|null
     */
    private function normalizedCoordinates(): ?array
    {
        $value = $this->coordinates;

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $this->castCoordinatePairs($decoded) : null;
        }

        if (! is_array($value)) {
            return null;
        }

        return $this->castCoordinatePairs($value);
    }

    /**
     * @param  array<mixed>  $coordinates
     * @return list<list<float>>|null
     */
    private function castCoordinatePairs(array $coordinates): ?array
    {
        $pairs = [];

        foreach ($coordinates as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }

            $pairs[] = [(float) $point[0], (float) $point[1]];
        }

        return count($pairs) >= 3 ? $pairs : (count($pairs) ? $pairs : null);
    }
}
