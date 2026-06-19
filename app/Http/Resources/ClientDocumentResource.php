<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ClientDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'type' => $this->type,
            'type_label' => ClientDocument::typeLabel($this->type),
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'version' => (int) $this->version,
            'is_current' => (bool) $this->is_current,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
