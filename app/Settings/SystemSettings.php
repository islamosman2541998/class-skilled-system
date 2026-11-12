<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SystemSettings extends Settings
{
    public ?int $trash_retention_days;

    public int $session_idle_minutes;

    public int $public_link_hours;

    public static function group(): string
    {
        return 'system';
    }
}
