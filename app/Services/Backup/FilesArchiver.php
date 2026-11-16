<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * A zip of the uploaded files (spec §13): the private disk (without backups/ and tmp/) under
 * "private/" and the public disk under "public/". Built with PHP's ZipArchive, no system command.
 */
class FilesArchiver
{
    private const SKIP = ['backups', 'tmp'];

    /**
     * @return int the file size in bytes
     */
    public function archive(string $path): int
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot write '.$path);
        }

        foreach (['private', 'public'] as $diskName) {
            $disk = Storage::disk($diskName);

            foreach ($disk->allFiles() as $file) {
                $top = explode('/', $file)[0];

                if ($diskName === 'private' && in_array($top, self::SKIP, true)) {
                    continue;
                }

                $zip->addFile($disk->path($file), $diskName.'/'.$file);
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('Cannot finish '.$path);
        }

        clearstatcache(true, $path);

        return (int) filesize($path);
    }
}
