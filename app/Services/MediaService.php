<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    private function disk(): Filesystem
    {
        return Storage::disk('r2');
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
        $path = "{$folder}/".Str::uuid().".{$ext}";

        $this->disk()->put($path, file_get_contents($file->getRealPath()), [
            'visibility' => 'public',
            'ContentType' => $file->getMimeType() ?? 'application/octet-stream',
        ]);

        $url = rtrim((string) config('filesystems.disks.r2.url'), '/').'/'.$path;

        if ($isCover) {
            $mediable->media()
                ->where('type', $type)
                ->update(['is_cover' => false]);
        }

        $nextOrder = ((int) $mediable->media()->where('type', $type)->max('order')) + 1;

        return $mediable->media()->create([
            'disk' => 'r2',
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
        if ($media->disk === 'r2') {
            try {
                if ($this->disk()->exists($media->path)) {
                    $this->disk()->delete($media->path);
                }
            } catch (\Exception $e) {
                Log::warning('MediaService::delete R2 error', [
                    'path' => $media->path,
                    'message' => $e->getMessage(),
                ]);
            }
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
