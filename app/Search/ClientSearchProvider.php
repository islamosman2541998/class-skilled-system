<?php

namespace App\Search;

use App\Models\Client;
use App\Models\User;
use App\Support\Like;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClientSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'clients';
    }

    public function labelKey(): string
    {
        return 'clients.clients';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('clients.view')) {
            return collect();
        }

        return Client::query()
            ->visibleTo($user)
            ->where(function (Builder $query) use ($term) {
                $query->orWhere(fn (Builder $q) => Like::any($q, ['name_ar', 'name_en', 'email', 'trn', 'phone'], $term));

                foreach (Phone::searchVariants($term) as $digits) {
                    $query->orWhere(fn (Builder $q) => Like::any($q, ['phone_normalized'], $digits));
                }
            })
            ->orderBy('name_ar')
            ->limit($limit)
            ->get()
            ->map(fn (Client $client) => new SearchResult($client->name, $client->phone, route('clients.show', $client)));
    }
}
