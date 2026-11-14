<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serves private attachments only to users who may see the owning record (spec §5.4, §12).
 */
class MediaController extends Controller
{
    private const MAX_AGE = 604800;

    public function show(Request $request, Media $media): BinaryFileResponse
    {
        $this->authorizeOwner($media, 'view');

        $inline = ! $request->boolean('download') && preg_match('#^(image/(jpeg|png|webp|gif)|video/|application/pdf)#', (string) $media->mime_type) === 1;

        return $this->send($media->getPath(), (string) $media->mime_type, $media->name.'.'.$media->extension, $inline);
    }

    public function thumb(Media $media): BinaryFileResponse
    {
        $this->authorizeOwner($media, 'view');

        $source = $media;

        if (! $media->hasGeneratedConversion('thumb')) {
            $source = $this->posters($media)->first();
        }

        abort_unless($source instanceof Media && $source->hasGeneratedConversion('thumb'), 404);

        return $this->send($source->getPath('thumb'), 'image/webp', $media->name.'.webp', true);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $this->authorizeOwner($media, 'update');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'category_id' => ['nullable', 'integer', 'exists:lookups,id'],
        ]);

        $media->name = $data['title'];

        if ($request->has('category_id')) {
            $media->setCustomProperty('category_id', $data['category_id'] ?? null);
        }

        $media->save();

        return response()->json(['message' => __('app.saved')]);
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->authorizeOwner($media, 'update');

        $this->posters($media)->get()->each->delete();

        $media->delete();

        return response()->json(['message' => __('uploads.deleted')]);
    }

    /**
     * Cover images captured for a video.
     *
     * @return Builder<Media>
     */
    private function posters(Media $media): Builder
    {
        return Media::query()
            ->where('model_type', $media->model_type)
            ->where('model_id', $media->model_id)
            ->where('collection_name', 'posters')
            ->where('custom_properties->media_id', $media->id);
    }

    private function authorizeOwner(Media $media, string $ability): void
    {
        $owner = $media->model;

        abort_unless($owner instanceof Model && method_exists($owner, 'attachmentPermission'), 404);
        // Records limited to their assignee (spec §6 "assigned only") hide their files too.
        abort_if(method_exists($owner, 'isVisibleTo') && ! $owner->isVisibleTo(auth()->user()), 404);

        Gate::authorize($owner->attachmentPermission().'.'.$ability);
    }

    private function send(string $path, string $mime, string $downloadName, bool $inline): BinaryFileResponse
    {
        abort_unless(is_file($path), 404);

        $response = response()->file($path, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // BinaryFileResponse marks itself public; these files must stay in the viewer's own cache.
        $response->setPrivate();
        $response->setMaxAge(self::MAX_AGE);

        $response->setContentDisposition(
            $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $downloadName,
            'file.'.pathinfo($path, PATHINFO_EXTENSION),
        );

        return $response;
    }
}
