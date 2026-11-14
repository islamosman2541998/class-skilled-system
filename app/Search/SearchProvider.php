<?php

namespace App\Search;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * One group of the global search (spec §5.6). Each module registers its own provider.
 */
interface SearchProvider
{
    public function key(): string;

    public function labelKey(): string;

    /**
     * Matches the user may see, at most $limit. Return an empty collection when the user lacks access.
     *
     * @return Collection<int, SearchResult>
     */
    public function search(string $term, User $user, int $limit = 5): Collection;
}
