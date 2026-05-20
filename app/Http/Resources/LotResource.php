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
            'number' => $this->number,
            'block' => $this->block,
            'area' => $this->area !== null ? (float) $this->area : null,
            'total_value' => $this->total_value !== null ? (float) $this->total_value : null,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
