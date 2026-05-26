<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    /**
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('order');
    }

    /**
     * @return MorphMany<Media, $this>
     */
    public function photos(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')
            ->where('type', Media::TYPE_PHOTO)
            ->orderBy('order');
    }

    public function coverPhoto(): ?Media
    {
        return $this->media()
            ->where('type', Media::TYPE_PHOTO)
            ->where('is_cover', true)
            ->first()
            ?? $this->media()
                ->where('type', Media::TYPE_PHOTO)
                ->first();
    }
}
