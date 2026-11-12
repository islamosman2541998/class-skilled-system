<?php

namespace App\Support;

use App\Settings\AppearanceSettings;

/**
 * Turns the appearance settings into CSS custom properties and the list of font families to load.
 */
class Theme
{
    /** @var array<string, string> */
    private const STACKS = [
        'cairo' => "'Cairo', system-ui, -apple-system, 'Segoe UI', sans-serif",
        'tajawal' => "'Tajawal', system-ui, -apple-system, 'Segoe UI', sans-serif",
    ];

    /**
     * @return array<string, string>
     */
    public static function cssVariables(): array
    {
        $settings = app(AppearanceSettings::class);

        return [
            '--c-navy' => $settings->color_navy,
            '--c-deep' => $settings->color_deep,
            '--c-sky' => $settings->color_sky,
            '--c-ink' => $settings->color_ink,
            '--c-muted' => $settings->color_muted,
            '--c-line' => $settings->color_line,
            '--c-paper' => $settings->color_paper,
            '--c-navy-rgb' => self::hexToRgb($settings->color_navy),
            '--c-sky-rgb' => self::hexToRgb($settings->color_sky),
            '--bs-primary' => $settings->color_navy,
            '--bs-primary-rgb' => self::hexToRgb($settings->color_navy),
            '--font-body' => self::STACKS[self::bodyFont()],
            '--font-heading' => self::STACKS[self::headingFont()],
        ];
    }

    /**
     * Font families that must be loaded for the current settings, body font first.
     *
     * @return list<string>
     */
    public static function fonts(): array
    {
        return array_values(array_unique([self::bodyFont(), self::headingFont()]));
    }

    public static function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return implode(', ', [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))]);
    }

    private static function bodyFont(): string
    {
        $font = app(AppearanceSettings::class)->primary_font;

        return isset(self::STACKS[$font]) ? $font : 'cairo';
    }

    private static function headingFont(): string
    {
        if (app(AppearanceSettings::class)->heading_font !== 'other') {
            return self::bodyFont();
        }

        return self::bodyFont() === 'cairo' ? 'tajawal' : 'cairo';
    }
}
