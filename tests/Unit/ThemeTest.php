<?php

use App\Settings\AppearanceSettings;
use App\Support\Theme;

function appearance(array $values): void
{
    $settings = app(AppearanceSettings::class);

    foreach ($values as $key => $value) {
        $settings->{$key} = $value;
    }

    $settings->save();
}

it('maps settings to css variables', function () {
    expect(Theme::cssVariables())->toMatchArray([
        '--c-navy' => '#2B4168',
        '--c-deep' => '#0C122B',
        '--c-sky' => '#4FC3EE',
        '--bs-primary' => '#2B4168',
        '--bs-primary-rgb' => '43, 65, 104',
    ]);
});

it('loads only the primary font', function () {
    expect(Theme::fonts())->toBe(['cairo'])
        ->and(Theme::cssVariables()['--font-body'])->toContain('Cairo')
        ->and(Theme::cssVariables()['--font-heading'])->toContain('Cairo');
});

it('loads both fonts when the heading font is the other one', function () {
    appearance(['heading_font' => 'other']);

    expect(Theme::fonts())->toBe(['cairo', 'tajawal'])
        ->and(Theme::cssVariables()['--font-heading'])->toContain('Tajawal');
});

it('switches the primary font to tajawal', function () {
    appearance(['primary_font' => 'tajawal']);

    expect(Theme::fonts())->toBe(['tajawal'])
        ->and(Theme::cssVariables()['--font-body'])->toContain('Tajawal');
});

it('follows a changed colour', function () {
    appearance(['color_navy' => '#112233']);

    expect(Theme::cssVariables())->toMatchArray(['--c-navy' => '#112233', '--bs-primary-rgb' => '17, 34, 51']);
});
