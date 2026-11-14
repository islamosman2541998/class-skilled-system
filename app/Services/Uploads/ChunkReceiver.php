<?php

namespace App\Services\Uploads;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Applies one chunk of FilePond's chunk protocol to a transfer. A chunk must start exactly at the
 * stored offset, so an interrupted upload resumes where the server stopped (spec §5.4).
 */
class ChunkReceiver
{
    /**
     * @throws HttpException 409 on an offset mismatch, 422 when the chunk overruns the declared length
     */
    public function receive(TemporaryUpload $upload, int $offset, string $bytes, ?int $declaredLength = null, ?string $name = null): void
    {
        if ($offset !== $upload->offset()) {
            throw new HttpException(409, '', null, ['Upload-Offset' => (string) $upload->offset()]);
        }

        if (($declaredLength !== null && $declaredLength !== $upload->size()) || $offset + strlen($bytes) > $upload->size()) {
            throw new HttpException(422, __('uploads.length_mismatch'));
        }

        if ($name !== null && $name !== '' && $upload->name() === '') {
            $upload->saveMeta(['name' => self::cleanName($name)]);
        }

        $upload->append($bytes);
    }

    /** Header values arrive URL-encoded so Arabic names survive; path parts are dropped. */
    public static function cleanName(string $name): string
    {
        $name = rawurldecode($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $parts = explode('/', str_replace('\\', '/', $name));

        // basename() is locale dependent and can drop leading Arabic characters.
        return mb_substr(trim((string) end($parts)), 0, 190);
    }
}
