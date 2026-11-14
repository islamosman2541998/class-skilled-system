<?php

namespace App\Services\Uploads;

use App\Enums\UploadKind;
use App\Exceptions\InvalidUploadException;
use App\Settings\UploadSettings;
use finfo;

/**
 * Decides what a finished upload is by its real content, not its name (spec §5.4, §12).
 */
class UploadValidator
{
    /** Real MIME types accepted for each known extension. Unknown extensions only pass the blocklist. */
    private const MIMES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'heic' => ['image/heic', 'image/heif'],
        'mp4' => ['video/mp4', 'application/mp4'],
        'm4v' => ['video/mp4', 'video/x-m4v'],
        'mov' => ['video/quicktime', 'video/mp4'],
        'webm' => ['video/webm', 'audio/webm'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'dwg' => ['image/vnd.dwg', 'application/acad', 'application/x-dwg', 'application/x-acad', 'application/octet-stream'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv'],
    ];

    /** Content that is never stored, whatever its name says. */
    private const BLOCKED = [
        'text/x-php', 'application/x-php', 'text/html', 'application/x-dosexec', 'application/x-msdownload',
        'application/x-executable', 'application/x-sharedlib', 'text/x-shellscript', 'application/javascript',
        'text/javascript', 'image/svg+xml',
    ];

    public function __construct(private readonly UploadSettings $settings) {}

    /**
     * @throws InvalidUploadException
     */
    public function kind(string $path, string $originalName): UploadKind
    {
        $extension = self::extension($originalName);
        $kind = $this->kindForExtension($extension)
            ?? throw new InvalidUploadException(__('uploads.type_not_allowed', ['name' => $originalName]));

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! $this->contentMatches($kind, $extension, $mime, $path)) {
            throw new InvalidUploadException(__('uploads.content_mismatch', ['name' => $originalName]));
        }

        $limit = $this->maxBytes($kind);

        if ((int) filesize($path) > $limit) {
            throw new InvalidUploadException(__('uploads.too_large', ['name' => $originalName, 'max' => $limit / 1048576]));
        }

        return $kind;
    }

    public function kindForExtension(string $extension): ?UploadKind
    {
        return match (true) {
            in_array($extension, $this->settings->image_types, true) => UploadKind::Image,
            in_array($extension, $this->settings->video_types, true) => UploadKind::Video,
            in_array($extension, $this->settings->file_types, true) => UploadKind::File,
            default => null,
        };
    }

    public function maxBytes(UploadKind $kind): int
    {
        $mb = match ($kind) {
            UploadKind::Image => $this->settings->image_max_mb,
            UploadKind::Video => $this->settings->video_max_mb,
            UploadKind::File => $this->settings->file_max_mb,
        };

        return $mb * 1048576;
    }

    /** The largest file any kind may be; a transfer above it is refused before it starts. */
    public function largestBytes(): int
    {
        return max(array_map(fn (UploadKind $kind) => $this->maxBytes($kind), UploadKind::cases()));
    }

    /**
     * Allowed extensions, as ".ext", for the browser-side check.
     *
     * @param  list<UploadKind>  $kinds
     * @return list<string>
     */
    public function acceptList(array $kinds): array
    {
        $types = [];

        foreach ($kinds as $kind) {
            $list = match ($kind) {
                UploadKind::Image => $this->settings->image_types,
                UploadKind::Video => $this->settings->video_types,
                UploadKind::File => $this->settings->file_types,
            };
            array_push($types, ...array_map(fn (string $ext) => '.'.$ext, $list));
        }

        return array_values(array_unique($types));
    }

    public static function extension(string $name): string
    {
        return mb_strtolower((string) (preg_match('/\.([^.\/\\\\]+)$/u', $name, $m) ? $m[1] : ''));
    }

    private function contentMatches(UploadKind $kind, string $extension, string $mime, string $path): bool
    {
        if (in_array($mime, self::BLOCKED, true)) {
            return false;
        }

        if (isset(self::MIMES[$extension]) && ! in_array($mime, self::MIMES[$extension], true)) {
            return false;
        }

        return match ($kind) {
            UploadKind::Image => str_starts_with($mime, 'image/') && @getimagesize($path) !== false,
            UploadKind::Video => str_starts_with($mime, 'video/') || in_array($mime, ['application/mp4', 'audio/webm'], true),
            UploadKind::File => true,
        };
    }
}
