<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MediaService
{
    private function client(): StorageClient
    {
        return new StorageClient([
            'keyFilePath' => (string) config('filesystems.disks.gcs.key_file_path'),
            'projectId' => (string) config('filesystems.disks.gcs.project_id'),
        ]);
    }

    private function bucket(): Bucket
    {
        return $this->client()->bucket((string) config('filesystems.disks.gcs.bucket'));
    }

    public function upload(
        UploadedFile $file,
        Model $mediable,
        string $folder,
        string $type = Media::TYPE_PHOTO,
        ?string $caption = null,
        bool $isCover = false,
    ): Media {
        $ext = $file->getClientOriginalExtension();
        $filename = $file->getClientOriginalName();
        $path = "{$folder}/" . Str::uuid() . ".{$ext}";

        $bucket = $this->bucket();
        $bucket->upload(
            fopen($file->getRealPath(), 'r'),
            [
                'name' => $path,
                'predefinedAcl' => 'publicRead',
                'metadata' => ['contentType' => $file->getMimeType()],
            ],
        );

        $url = rtrim((string) config('filesystems.disks.gcs.url'), '/') . '/' . $path;

        if ($isCover) {
            $mediable->media()
                ->where('type', $type)
                ->update(['is_cover' => false]);
        }

        $nextOrder = ((int) $mediable->media()->where('type', $type)->max('order')) + 1;

        return $mediable->media()->create([
            'disk' => 'gcs',
            'path' => $path,
            'url' => $url,
            'filename' => $filename,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'type' => $type,
            'size' => $file->getSize(),
            'order' => $nextOrder,
            'caption' => $caption,
            'is_cover' => $isCover,
        ]);
    }

    public function delete(Media $media): void
    {
        try {
            $bucket = $this->bucket();
            $object = $bucket->object($media->path);

            if ($object->exists()) {
                $object->delete();
            }
        } catch (\Exception $e) {
            Log::warning('MediaService::delete GCS error', [
                'path' => $media->path,
                'message' => $e->getMessage(),
            ]);
        }

        $media->delete();
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $order => $id) {
            Media::query()->where('id', $id)->update(['order' => $order]);
        }
    }

    public function setCover(Media $media): void
    {
        Media::query()
            ->where('mediable_type', $media->mediable_type)
            ->where('mediable_id', $media->mediable_id)
            ->where('type', $media->type)
            ->update(['is_cover' => false]);

        $media->update(['is_cover' => true]);
    }
}
