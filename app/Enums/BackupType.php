<?php

namespace App\Enums;

/** What a backup holds (spec §13): the database (daily) or the uploaded files (on demand). */
enum BackupType: string
{
    case Database = 'database';
    case Files = 'files';

    public function label(): string
    {
        return __('settings.backups.types.'.$this->value);
    }

    /** File name start and extension under backups/. */
    public function fileName(string $stamp): string
    {
        return $this === self::Database ? "db-{$stamp}.sql.gz" : "files-{$stamp}.zip";
    }
}
