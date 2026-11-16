<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SystemSettings extends Settings
{
    public ?int $trash_retention_days;

    public int $session_idle_minutes;

    public int $public_link_hours;

    /** @var list<int> ISO weekdays (1 = Monday ... 7 = Sunday) not counted as days without a daily report. */
    public array $weekly_days_off;

    /** Successful backups kept of each type (spec §13). */
    public int $backup_keep;

    public static function group(): string
    {
        return 'system';
    }
}
