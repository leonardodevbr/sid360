<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\ClientDocument;
use Illuminate\Support\Facades\Storage;

class DeleteClientDocumentAction
{
    /**
     * Exclusão definitiva (não soft delete): remove o arquivo no disco e a
     * linha do banco. Cópias já congeladas em sale_documents não são afetadas
     * — client_document_id naquela tabela apenas perde a referência (nullOnDelete).
     */
    public function execute(ClientDocument $document): void
    {
        $disk = Storage::disk($document->disk);

        if ($disk->exists($document->path)) {
            $disk->delete($document->path);
        }

        $document->delete();
    }
}
