<?php

namespace App\Search;

use App\Models\Lead;
use App\Models\User;
use App\Support\Like;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LeadSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'leads';
    }

    public function labelKey(): string
    {
        return 'leads.leads';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('leads.view')) {
            return collect();
        }

        return Lead::query()
            ->with('client')
            ->visibleTo($user)
            ->where(function (Builder $query) use ($term) {
                $query->orWhere(fn (Builder $q) => Like::any($q, ['number', 'contact_name', 'contact_phone', 'client.name_ar', 'client.name_en'], $term));

                foreach (Phone::searchVariants($term) as $digits) {
                    $query->orWhere(fn (Builder $q) => Like::any($q, ['contact_phone_normalized'], $digits));
                }
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead) => new SearchResult($lead->number.' · '.$lead->displayName(), $lead->contact_phone, route('leads.show', $lead)));
    }
}
