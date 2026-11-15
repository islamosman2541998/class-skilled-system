<?php

namespace App\Search;

use App\Models\Claim;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class ClaimSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'claims';
    }

    public function labelKey(): string
    {
        return 'site.claims.claims';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('claims.view')) {
            return collect();
        }

        return Like::any(Claim::query()->with(['project', 'client'])->visibleTo($user), ['number'], $term)
            ->latest('period_to')
            ->limit($limit)
            ->get()
            ->map(fn (Claim $claim) => new SearchResult(
                ($claim->number ?? __('site.claims.statuses.draft')).' · '.$claim->project->number,
                $claim->client->name.' · '.money($claim->total),
                route('claims.show', $claim),
            ));
    }
}
