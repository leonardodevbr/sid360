<?php

declare(strict_types=1);

namespace App\Actions\Sale;

use App\Models\Sale;
use App\Models\SaleDocument;
use Illuminate\Support\Facades\Storage;

class SnapshotSaleDocumentsAction
{
    /**
     * Copia os documentos atuais (is_current = true) do cliente da venda para
     * sale_documents, em um caminho isolado por empreendimento — "congela" o
     * estado dos documentos no momento da venda. Se o cliente atualizar um
     * documento depois (nova versão no perfil geral), a cópia já feita nesta
     * venda não é afetada. Idempotente: não duplica documentos já copiados.
     */
    public function execute(Sale $sale): Sale
    {
        $sale->loadMissing(['client.currentDocuments', 'lot.development']);

        $client = $sale->client;
        $development = $sale->lot?->development;

        if (! $client || ! $development) {
            return $sale;
        }

        foreach ($client->currentDocuments as $clientDocument) {
            $alreadyCopied = $sale->documents()
                ->where('client_document_id', $clientDocument->id)
                ->exists();

            if ($alreadyCopied) {
                continue;
            }

            $extension = strtolower(pathinfo((string) $clientDocument->path, PATHINFO_EXTENSION) ?: 'bin');
            $destinationPath = "developments/{$development->id}/sales/{$sale->id}/documents/{$clientDocument->type}/{$clientDocument->side}/v{$clientDocument->version}.{$extension}";

            $disk = Storage::disk($clientDocument->disk);

            if ($disk->exists($clientDocument->path)) {
                $disk->copy($clientDocument->path, $destinationPath);
                // Garante visibilidade privada na cópia, independente do que o
                // driver S3 fizer com a ACL durante o copyObject.
                $disk->setVisibility($destinationPath, 'private');
            }

            SaleDocument::query()->create([
                'sale_id' => $sale->id,
                'client_document_id' => $clientDocument->id,
                'type' => $clientDocument->type,
                'side' => $clientDocument->side,
                'disk' => $clientDocument->disk,
                'path' => $destinationPath,
                'original_filename' => $clientDocument->original_filename,
                'mime_type' => $clientDocument->mime_type,
                'size' => $clientDocument->size,
                'uploaded_by' => $clientDocument->uploaded_by,
            ]);
        }

        return $sale;
    }
}
