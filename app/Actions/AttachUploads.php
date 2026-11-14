<?php

namespace App\Actions;

use App\Enums\UploadKind;
use App\Exceptions\InvalidUploadException;
use App\Models\User;
use App\Services\Uploads\TemporaryUpload;
use App\Services\Uploads\UploadValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Moves finished transfers onto a record as media (spec §5.4). Every transfer is checked before
 * any is attached; a file whose content is not allowed is deleted with its temp folder.
 */
class AttachUploads
{
    public function __construct(private readonly UploadValidator $validator) {}

    /**
     * @param  list<string>  $uploadIds
     * @param  array{title?: ?string, category_id?: ?int}  $meta
     * @return Collection<int, Media>
     *
     * @throws ValidationException
     */
    public function handle(HasMedia $model, array $uploadIds, User $by, array $meta = [], string $collection = 'attachments'): Collection
    {
        $checked = [];

        foreach (array_unique($uploadIds) as $id) {
            $upload = TemporaryUpload::find((string) $id);

            if ($upload === null || $upload->ownerId() !== $by->id) {
                throw ValidationException::withMessages(['uploads' => __('uploads.not_found')]);
            }

            if (! $upload->isComplete()) {
                throw ValidationException::withMessages(['uploads' => __('uploads.incomplete', ['name' => $upload->name()])]);
            }

            try {
                $checked[] = [$upload, $this->validator->kind($upload->path(), $upload->name())];
            } catch (InvalidUploadException $e) {
                $upload->delete();

                throw ValidationException::withMessages(['uploads' => $e->getMessage()]);
            }
        }

        $title = isset($meta['title']) && trim((string) $meta['title']) !== '' ? trim((string) $meta['title']) : null;
        $categoryId = isset($meta['category_id']) ? (int) $meta['category_id'] : null;

        return collect($checked)->map(function (array $pair) use ($model, $by, $title, $categoryId, $collection) {
            /** @var TemporaryUpload $upload */
            [$upload, $kind] = $pair;

            $media = $this->addMedia($model, $upload, $kind, $by, $title, $categoryId, $collection);
            $upload->delete();

            return $media;
        });
    }

    private function addMedia(HasMedia $model, TemporaryUpload $upload, UploadKind $kind, User $by, ?string $title, ?int $categoryId, string $collection): Media
    {
        $extension = UploadValidator::extension($upload->name());
        $name = $title ?? (preg_replace('/\.[^.]+$/u', '', $upload->name()) ?: $upload->name());

        $media = $model->addMedia($upload->path())
            ->usingName(mb_substr($name, 0, 190))
            ->usingFileName(Str::uuid().'.'.$extension)
            ->withCustomProperties([
                'kind' => $kind->value,
                'category_id' => $categoryId,
                'uploaded_by' => $by->id,
            ])
            ->toMediaCollection($collection, 'private');

        if ($kind === UploadKind::Video && ($poster = $upload->posterPath()) !== null && @getimagesize($poster) !== false) {
            $model->addMedia($poster)
                ->usingName($media->name)
                ->usingFileName(Str::uuid().'.jpg')
                ->withCustomProperties(['media_id' => $media->id, 'uploaded_by' => $by->id])
                ->toMediaCollection('posters', 'private');
        }

        return $media;
    }
}
