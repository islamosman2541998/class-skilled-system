<?php

namespace App\Enums;

enum UploadKind: string
{
    case Image = 'image';
    case Video = 'video';
    case File = 'file';

    public function label(): string
    {
        return __('uploads.kinds.'.$this->value);
    }
}
