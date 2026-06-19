<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Client\DeleteClientAction;
use App\Actions\Client\DeleteClientDocumentAction;
use App\Actions\Client\ListClientDocumentsAction;
use App\Actions\Client\ListClientsAction;
use App\Actions\Client\StoreClientAction;
use App\Actions\Client\UpdateClientAction;
use App\Actions\Client\UploadClientDocumentAction;
use App\Http\Requests\PatchClientWhatsappStatusRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Requests\UploadClientDocumentRequest;
use App\Http\Resources\ClientDocumentResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function index(Request $request, ListClientsAction $action): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('clients.view');

        $result = $action->execute($request);

        if ($request->boolean('all')) {
            return response()->json(ClientResource::collection($result));
        }

        return ClientResource::collection($result);
    }

    public function store(StoreClientRequest $request, StoreClientAction $action): JsonResponse
    {
        $this->authorize('clients.create');

        $client = $action->execute($request->validated());

        return response()->json(new ClientResource($client), 201);
    }

    public function show(string|int $id): JsonResponse
    {
        $this->authorize('clients.view');

        $client = Client::query()->findOrFail((int) $id);

        return response()->json(new ClientResource($client));
    }

    public function update(UpdateClientRequest $request, string|int $id, UpdateClientAction $action): JsonResponse
    {
        $this->authorize('clients.edit');

        $client = Client::query()->findOrFail((int) $id);
        $client = $action->execute($client, $request->validated());

        return response()->json(new ClientResource($client));
    }

    public function destroy(string|int $id, DeleteClientAction $action): JsonResponse
    {
        $this->authorize('clients.delete');

        $client = Client::query()->findOrFail((int) $id);
        $action->execute($client);

        return response()->json(['message' => 'Cliente excluído com sucesso.']);
    }

    public function documents(string|int $id, ListClientDocumentsAction $action): AnonymousResourceCollection
    {
        $this->authorize('clients.view');

        $client = Client::query()->findOrFail((int) $id);

        return ClientDocumentResource::collection($action->execute($client));
    }

    public function uploadDocument(
        UploadClientDocumentRequest $request,
        string|int $id,
        UploadClientDocumentAction $action
    ): JsonResponse {
        $this->authorize('clients.edit');

        $client = Client::query()->findOrFail((int) $id);

        $document = $action->execute(
            $client,
            $request->file('file'),
            (string) $request->validated('type'),
            $request->user()?->id,
        );

        return response()->json(new ClientDocumentResource($document), 201);
    }

    public function downloadDocument(string|int $id, string|int $documentId): StreamedResponse
    {
        $this->authorize('clients.view');

        $client = Client::query()->findOrFail((int) $id);
        $document = $client->documents()->findOrFail((int) $documentId);

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    public function deleteDocument(
        string|int $id,
        string|int $documentId,
        DeleteClientDocumentAction $action
    ): JsonResponse {
        $this->authorize('clients.edit');

        $client = Client::query()->findOrFail((int) $id);
        $document = $client->documents()->findOrFail((int) $documentId);
        $action->execute($document);

        return response()->json(['message' => 'Documento excluído com sucesso.']);
    }

    public function updateWhatsappStatus(
        PatchClientWhatsappStatusRequest $request,
        string|int $id
    ): JsonResponse {
        $client = Client::query()->findOrFail((int) $id);
        $client->update([
            'whatsapp_status' => $request->validated('status'),
        ]);

        return response()->json([
            'ok' => true,
            'data' => new ClientResource($client->fresh()),
        ]);
    }
}
