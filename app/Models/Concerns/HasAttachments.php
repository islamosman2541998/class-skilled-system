<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attachments on the private disk with a WebP thumbnail for images (spec §5.4).
 * The owning model must implement Spatie\MediaLibrary\HasMedia.
 */
trait HasAttachments
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('private');
        $this->addMediaCollection('posters')->useDisk('private');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media !== null && ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->performOnCollections('attachments', 'posters')
            ->format('webp')
            ->fit(Fit::Max, 400, 400);
    }

    /** Permission prefix that guards this record's files: "{prefix}.view" to see, "{prefix}.update" to change. */
    public function attachmentPermission(): string
    {
        return $this->getTable();
    }
}
