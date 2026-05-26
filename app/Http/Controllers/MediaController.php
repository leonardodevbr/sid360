<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Development;
use App\Models\Lot;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    public function uploadLot(Request $request, string|int $lotId): JsonResponse
    {
        $this->authorize('lots.edit');

        $lot = Lot::query()->findOrFail((int) $lotId);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:51200'],
            'caption' => ['nullable', 'string', 'max:200'],
            'is_cover' => ['nullable', 'boolean'],
        ]);

        $mimeType = (string) $request->file('file')?->getMimeType();
        $type = str_starts_with($mimeType, 'video/')
            ? Media::TYPE_VIDEO
            : Media::TYPE_PHOTO;

        $media = $this->mediaService->upload(
            file: $request->file('file'),
            mediable: $lot,
            folder: "lots/{$lot->id}",
            type: $type,
            caption: $request->input('caption'),
            isCover: (bool) $request->input('is_cover', false),
        );

        return response()->json($this->resource($media), 201);
    }

    public function uploadDevelopment(Request $request, string|int $devId): JsonResponse
    {
        $this->authorize('developments.edit');

        $dev = Development::query()->findOrFail((int) $devId);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:102400'],
            'caption' => ['nullable', 'string', 'max:200'],
            'is_cover' => ['nullable', 'boolean'],
        ]);

        $mimeType = (string) $request->file('file')?->getMimeType();
        $type = str_starts_with($mimeType, 'video/')
            ? Media::TYPE_VIDEO
            : Media::TYPE_PHOTO;

        $media = $this->mediaService->upload(
            file: $request->file('file'),
            mediable: $dev,
            folder: "developments/{$dev->id}",
            type: $type,
            caption: $request->input('caption'),
            isCover: (bool) $request->input('is_cover', false),
        );

        return response()->json($this->resource($media), 201);
    }

    public function indexLot(string|int $lotId): JsonResponse
    {
        $this->authorize('lots.view');

        $lot = Lot::query()->findOrFail((int) $lotId);
        $media = $lot->media()->get();

        return response()->json($media->map(fn (Media $item): array => $this->resource($item)));
    }

    public function indexDevelopment(string|int $devId): JsonResponse
    {
        $this->authorize('developments.view');

        $dev = Development::query()->findOrFail((int) $devId);
        $media = $dev->media()->get();

        return response()->json($media->map(fn (Media $item): array => $this->resource($item)));
    }

    public function destroy(string|int $id): JsonResponse
    {
        $media = Media::query()->findOrFail((int) $id);
        $this->authorizeMediaMutation($media);

        $this->mediaService->delete($media);

        return response()->json(['message' => 'Mídia excluída.']);
    }

    public function setCover(string|int $id): JsonResponse
    {
        $media = Media::query()->findOrFail((int) $id);
        $this->authorizeMediaMutation($media);

        $this->mediaService->setCover($media);

        return response()->json(['message' => 'Capa definida.']);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        $media = Media::query()->findOrFail((int) $id);
        $this->authorizeMediaMutation($media);

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:200'],
            'order' => ['nullable', 'integer', 'min:0'],
        ]);

        $media->update($validated);

        return response()->json($this->resource($media->fresh()));
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $mediaItems = Media::query()->whereIn('id', $request->input('ids'))->get();

        if ($mediaItems->isEmpty()) {
            return response()->json(['message' => 'Nenhuma mídia encontrada.'], 422);
        }

        $mediableType = $mediaItems->first()->mediable_type;
        $mediableId = $mediaItems->first()->mediable_id;

        if ($mediaItems->contains(fn (Media $item): bool => $item->mediable_type !== $mediableType || $item->mediable_id !== $mediableId)) {
            return response()->json(['message' => 'As mídias devem pertencer ao mesmo registro.'], 422);
        }

        $this->authorizeMediaMutation($mediaItems->first());

        $this->mediaService->reorder($request->input('ids'));

        return response()->json(['message' => 'Ordem atualizada.']);
    }

    private function authorizeMediaMutation(Media $media): void
    {
        if ($media->mediable_type === Lot::class) {
            $this->authorize('lots.edit');

            return;
        }

        if ($media->mediable_type === Development::class) {
            $this->authorize('developments.edit');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url,
            'filename' => $media->filename,
            'mime_type' => $media->mime_type,
            'type' => $media->type,
            'size' => $media->size,
            'order' => $media->order,
            'caption' => $media->caption,
            'is_cover' => $media->is_cover,
        ];
    }
}
