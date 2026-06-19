<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadClientDocumentAction
{
    /**
     * Nunca sobrescreve um documento existente: a versão atual desse tipo é
     * marcada como is_current = false e uma nova linha é criada com a versão
     * incrementada. Histórico fica preservado mesmo quando o cliente atualiza
     * um documento (ex.: RG vencido).
     */
    public function execute(Client $client, UploadedFile $file, string $type, ?int $uploadedBy = null): ClientDocument
    {
        $nextVersion = ((int) $client->documents()->where('type', $type)->max('version')) + 1;

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = "clients/{$client->id}/documents/{$type}/v{$nextVersion}.{$extension}";

        // Disco r2 é 'public' por padrão (ver MediaService); documentos sensíveis
        // (RG/CPF/CNH etc.) precisam de visibility 'private' por objeto, sem URL pública.
        Storage::disk('r2')->put($path, file_get_contents($file->getRealPath()), [
            'visibility' => 'private',
            'ContentType' => $file->getMimeType() ?? 'application/octet-stream',
        ]);

        $client->documents()
            ->where('type', $type)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        return $client->documents()->create([
            'type' => $type,
            'disk' => 'r2',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'version' => $nextVersion,
            'is_current' => true,
            'uploaded_by' => $uploadedBy,
        ]);
    }
}
