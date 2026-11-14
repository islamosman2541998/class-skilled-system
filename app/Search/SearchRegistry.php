<?php

namespace App\Search;

use App\Models\User;

/**
 * The providers of the global search, in registration order (singleton).
 */
class SearchRegistry
{
    /** @var array<string, SearchProvider> */
    private array $providers = [];

    public function register(SearchProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    /**
     * @return list<array{key: string, label: string, results: list<array{title: string, subtitle: ?string, url: string}>}>
     */
    public function search(string $term, User $user): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $groups = [];

        foreach ($this->providers as $provider) {
            $results = $provider->search($term, $user);

            if ($results->isNotEmpty()) {
                $groups[] = [
                    'key' => $provider->key(),
                    'label' => __($provider->labelKey()),
                    'results' => $results->map(fn (SearchResult $result) => $result->toArray())->values()->all(),
                ];
            }
        }

        return $groups;
    }
}
