<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class UploadSettings extends Settings
{
    public int $image_max_mb;

    public int $video_max_mb;

    public int $file_max_mb;

    /** @var list<string> */
    public array $image_types;

    /** @var list<string> */
    public array $video_types;

    /** @var list<string> */
    public array $file_types;

    public int $image_max_dimension;

    public int $chunk_size_mb;

    public static function group(): string
    {
        return 'uploads';
    }
}
