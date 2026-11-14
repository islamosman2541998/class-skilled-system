<?php

namespace App\Services\Uploads;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One file transfer in progress (spec §5.4). Lives on the private disk under
 * tmp-uploads/{id}: the bytes in "data", the state in "meta.json" and an optional "poster.jpg".
 */
final class TemporaryUpload
{
    public const ROOT = 'tmp-uploads';

    /** @var array{name: string, size: int, offset: int, user_id: int, created_at: int} */
    private array $meta;

    /**
     * @param  array{name: string, size: int, offset: int, user_id: int, created_at: int}  $meta
     */
    private function __construct(public readonly string $id, array $meta)
    {
        $this->meta = $meta;
    }

    public static function create(int $ownerId, int $size, string $name = ''): self
    {
        $upload = new self((string) Str::uuid(), [
            'name' => $name,
            'size' => $size,
            'offset' => 0,
            'user_id' => $ownerId,
            'created_at' => now()->getTimestamp(),
        ]);

        File::ensureDirectoryExists($upload->directory());
        file_put_contents($upload->path(), '');
        $upload->writeMeta();

        return $upload;
    }

    public static function find(string $id): ?self
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $metaPath = self::disk()->path(self::ROOT."/{$id}/meta.json");

        if (! is_file($metaPath)) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($metaPath), true);

        return is_array($meta) ? new self($id, $meta) : null;
    }

    public static function disk(): Filesystem
    {
        return Storage::disk('private');
    }

    public function directory(): string
    {
        return self::disk()->path(self::ROOT.'/'.$this->id);
    }

    public function path(): string
    {
        return $this->directory().'/data';
    }

    public function posterPath(): ?string
    {
        $path = $this->directory().'/poster.jpg';

        return is_file($path) ? $path : null;
    }

    public function name(): string
    {
        return $this->meta['name'];
    }

    public function size(): int
    {
        return $this->meta['size'];
    }

    public function offset(): int
    {
        return $this->meta['offset'];
    }

    public function ownerId(): int
    {
        return $this->meta['user_id'];
    }

    public function createdAt(): int
    {
        return $this->meta['created_at'];
    }

    public function isComplete(): bool
    {
        return $this->offset() === $this->size() && filesize($this->path()) === $this->size();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function saveMeta(array $values): void
    {
        $this->meta = array_merge($this->meta, $values);
        $this->writeMeta();
    }

    /**
     * Appends bytes at the end of the data file and moves the offset forward.
     */
    public function append(string $bytes): void
    {
        file_put_contents($this->path(), $bytes, FILE_APPEND | LOCK_EX);
        clearstatcache(true, $this->path());
        $this->saveMeta(['offset' => (int) filesize($this->path())]);
    }

    public function delete(): void
    {
        File::deleteDirectory($this->directory());
    }

    private function writeMeta(): void
    {
        file_put_contents($this->directory().'/meta.json', json_encode($this->meta, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
