<?php

use Illuminate\Support\Facades\File;

/**
 * Every user-facing string must come from the lang files (spec §5.8).
 * Views may contain markup, Blade expressions and attributes, but no literal sentences.
 */
function visibleTemplateText(string $source): string
{
    $source = preg_replace('/\{\{--.*?--\}\}/s', ' ', $source);
    $source = preg_replace('/<(script|style)\b.*?<\/\1>/si', ' ', $source);
    $source = preg_replace('/@(verbatim|php)\b.*?@end\1/s', ' ', $source);
    $source = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', ' ', $source);
    $source = preg_replace('/@\w+\s*(\((?:[^()]++|(?1))*\))?/', ' ', $source);
    $source = preg_replace('/<[^>]*>/s', ' ', $source);

    return html_entity_decode($source);
}

it('has no Arabic literals in views', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (preg_match('/\p{Arabic}/u', $file->getContents())) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('has no literal sentences in views', function () {
    $offenders = [];

    foreach (File::allFiles(resource_path('views')) as $file) {
        if (preg_match('/[A-Za-z]{2,}(\s+[A-Za-z]{2,}){2,}/', visibleTemplateText($file->getContents()), $match)) {
            $offenders[] = $file->getRelativePathname().': '.$match[0];
        }
    }

    expect($offenders)->toBe([]);
});
