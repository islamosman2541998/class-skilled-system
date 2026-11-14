<?php

namespace App\Search;

use App\Models\Quotation;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class QuotationSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'quotations';
    }

    public function labelKey(): string
    {
        return 'quotations.quotations';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('quotations.view')) {
            return collect();
        }

        return Like::any(Quotation::query()->with('client')->visibleTo($user), ['number', 'title_ar', 'title_en', 'client.name_ar', 'client.name_en'], $term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Quotation $q) => new SearchResult($q->number.' · '.$q->title, $q->client->name.' · '.money($q->total), route('quotations.show', $q)));
    }
}
