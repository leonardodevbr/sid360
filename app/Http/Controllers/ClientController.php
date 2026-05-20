<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Client\DeleteClientAction;
use App\Actions\Client\ListClientsAction;
use App\Actions\Client\StoreClientAction;
use App\Actions\Client\UpdateClientAction;
use App\Http\Requests\PatchClientWhatsappStatusRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
