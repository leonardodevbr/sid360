<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Models\Client;
use Illuminate\Database\Eloquent\Collection;

class ListClientDocumentsAction
{
    /**
     * Lista todos os documentos do cliente (perfil geral), incluindo
     * versões antigas, ordenados por tipo e versão decrescente — a
     * versão atual de cada tipo aparece primeiro.
     *
     * @return Collection<int, \App\Models\ClientDocument>
     */
    public function execute(Client $client): Collection
    {
        return $client->documents()
            ->orderBy('type')
            ->orderBy('side')
            ->orderByDesc('version')
            ->get();
    }
}
