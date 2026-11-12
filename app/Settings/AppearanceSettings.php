<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AppearanceSettings extends Settings
{
    public string $color_navy;

    public string $color_deep;

    public string $color_sky;

    public string $color_ink;

    public string $color_muted;

    public string $color_line;

    public string $color_paper;

    public string $primary_font;

    public string $heading_font;

    public string $default_locale;

    public static function group(): string
    {
        return 'appearance';
    }
}
