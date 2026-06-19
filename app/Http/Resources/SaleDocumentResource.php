<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SaleDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'client_document_id' => $this->client_document_id,
            'type' => $this->type,
            'type_label' => SaleDocument::typeLabel($this->type),
            'side' => $this->side,
            'side_label' => SaleDocument::sideLabel($this->side),
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
