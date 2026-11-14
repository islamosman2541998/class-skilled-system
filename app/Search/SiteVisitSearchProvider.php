<?php

namespace App\Search;

use App\Models\SiteVisit;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class SiteVisitSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'site_visits';
    }

    public function labelKey(): string
    {
        return 'visits.visits';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('site_visits.view')) {
            return collect();
        }

        return Like::any(SiteVisit::query()->with('client')->visibleTo($user), ['number', 'client.name_ar', 'client.name_en'], $term)
            ->latest('scheduled_at')
            ->limit($limit)
            ->get()
            ->map(fn (SiteVisit $visit) => new SearchResult($visit->number.' · '.$visit->client->name, fmt_datetime($visit->scheduled_at), route('site-visits.show', $visit)));
    }
}
