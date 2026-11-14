<?php

namespace App\Search;

use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class UserSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'users';
    }

    public function labelKey(): string
    {
        return 'users.users';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('users.view')) {
            return collect();
        }

        return Like::any(User::query(), ['name', 'email', 'phone'], $term)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $match) => new SearchResult($match->name, $match->email, route('users.edit', $match)));
    }
}
