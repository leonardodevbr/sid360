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
            ]),
            'number' => $this->number,
            'block' => $this->block,
            'area' => $this->area !== null ? (float) $this->area : null,
            'area_computed' => $this->area_computed !== null ? (float) $this->area_computed : null,
            'coordinates' => $this->coordinates,
            'total_value' => $this->total_value !== null ? (int) $this->total_value : null,
            'down_payment_percent' => $this->down_payment_percent !== null
                ? (float) $this->down_payment_percent
                : null,
            'effective_down_payment_percent' => $this->effectiveDownPaymentPercent(),
            'uses_development_payment_terms' => $this->down_payment_percent === null,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
