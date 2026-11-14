<?php

namespace App\Search;

final readonly class SearchResult
{
    public function __construct(
        public string $title,
        public ?string $subtitle,
        public string $url,
    ) {}

    /**
     * @return array{title: string, subtitle: ?string, url: string}
     */
    public function toArray(): array
    {
        return ['title' => $this->title, 'subtitle' => $this->subtitle, 'url' => $this->url];
    }
}
