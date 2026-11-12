<?php

namespace App\View\Components;

use Illuminate\Support\Facades\File;
use Illuminate\View\Component;

/**
 * Inline Tabler outline icon. SVG files live in resources/svg/tabler; in local development a missing
 * icon is copied there from node_modules so only the icons actually used are shipped.
 */
class Icon extends Component
{
    /** @var array<string, string> */
    private static array $cache = [];

    public function __construct(public string $name, public int $size = 18) {}

    public function render(): string
    {
        return <<<'BLADE'
            <span {{ $attributes->merge(['class' => 'icon', 'aria-hidden' => 'true']) }}>{!! $svg() !!}</span>
            BLADE;
    }

    public function svg(): string
    {
        $key = $this->name.':'.$this->size;

        return self::$cache[$key] ??= $this->load();
    }

    private function load(): string
    {
        $path = resource_path("svg/tabler/{$this->name}.svg");

        if (! File::exists($path)) {
            $source = base_path("node_modules/@tabler/icons/icons/outline/{$this->name}.svg");

            if (app()->isProduction() || ! File::exists($source)) {
                return '';
            }

            File::ensureDirectoryExists(dirname($path));
            File::copy($source, $path);
        }

        $svg = preg_replace('/<!--.*?-->/s', '', File::get($path));
        $svg = preg_replace('/\s(width|height|class)="[^"]*"/', '', $svg, 3);

        return str_replace('<svg', '<svg width="'.$this->size.'" height="'.$this->size.'"', trim($svg));
    }
}
