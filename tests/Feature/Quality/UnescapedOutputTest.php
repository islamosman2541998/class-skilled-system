<?php

use Illuminate\Support\Facades\File;

use function Pest\Laravel\actingAs;

/**
 * Spec §12: no raw output with {!! !!}. The only exception is the theme partial, whose values are
 * colours validated as #RRGGBB by AppearanceSettingsRequest.
 */
const RAW_OUTPUT_ALLOWED = ['partials/theme.blade.php'];

it('uses unescaped Blade output only in the allowed files', function () {
    $root = resource_path('views').DIRECTORY_SEPARATOR;
    $found = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php') && str_contains((string) file_get_contents($file->getPathname()), '{!!'))
        ->map(fn ($file) => str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root))))
        ->reject(fn (string $path) => in_array($path, RAW_OUTPUT_ALLOWED, true))
        ->values()->all();

    expect($found)->toBe([]);
});

it('theme colours accept only #RRGGBB values', function () {
    actingAs(scopedUser(['settings.manage']))
        ->put(route('settings.update', 'appearance'), ['color_navy' => '#0B2545;}body{display:none'])
        ->assertSessionHasErrors('color_navy');
});
