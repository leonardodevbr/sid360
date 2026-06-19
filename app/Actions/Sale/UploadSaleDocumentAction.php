<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\ClientDocument;
use App\Models\Sale;
use App\Models\SaleDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadSaleDocumentAction
{
    /**
     * Upload de documento direto na venda (sem vínculo com client_documents)
     * — para documentos que só existem no contexto daquela venda/empreendimento
     * e não devem refletir no perfil geral do cliente.
     */
    public function execute(
        Sale $sale,
        UploadedFile $file,
        string $type,
        ?int $uploadedBy = null,
        string $side = ClientDocument::SIDE_ABERTO,
    ): SaleDocument {
        $sale->loadMissing('lot.development');
        $developmentId = $sale->lot?->development?->id ?? 'sem-empreendimento';

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = "developments/{$developmentId}/sales/{$sale->id}/documents/{$type}/{$side}/".Str::uuid().".{$extension}";

        Storage::disk('r2')->put($path, file_get_contents($file->getRealPath()), [
            'visibility' => 'private',
            'ContentType' => $file->getMimeType() ?? 'application/octet-stream',
        ]);

        return $sale->documents()->create([
            'client_document_id' => null,
            'type' => $type,
            'side' => $side,
            'disk' => 'r2',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $uploadedBy,
        ]);
    }
}
