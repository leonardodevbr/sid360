<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevelopmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hero = $this->heroVideo();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location,
            'status' => $this->status,
            'down_payment_percent' => $this->down_payment_percent !== null
                ? (float) $this->down_payment_percent
                : 20,
            'base_price_per_m2' => $this->base_price_per_m2 !== null ? (int) $this->base_price_per_m2 : null,
            'coordinates' => $this->coordinates,
            'lot_number_pattern' => $this->lot_number_pattern,
            'map_center' => $this->map_center,
            'map_zoom' => $this->map_zoom ?? 17,
            'map_bearing' => $this->map_bearing !== null ? (float) $this->map_bearing : 0,
            'map_color' => $this->map_color,
            'is_featured' => (bool) $this->is_featured,
            'cover_photo' => $this->coverPhoto()?->url,
            'hero_video_url' => $hero?->url,
            'hero_video_mime' => $hero?->mime_type,
            'photos_count' => $this->media()->where('type', 'photo')->count(),
            'zones' => $this->whenLoaded('zones', fn () => $this->zones->map(fn ($zone) => [
                'id' => $zone->id,
                'development_id' => $zone->development_id,
                'name' => $zone->name,
                'type' => $zone->type,
                'color' => $zone->color,
                'coordinates' => $zone->coordinates,
                'order' => $zone->order,
                'price_per_m2' => $zone->price_per_m2 !== null ? (int) $zone->price_per_m2 : null,
                'lots' => $zone->relationLoaded('lots')
                    ? LotResource::collection($zone->lots)
                    : [],
            ])),
            'lots_count' => $this->whenCounted('lots'),
            'available_lots_count' => $this->whenCounted('available_lots_count'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
