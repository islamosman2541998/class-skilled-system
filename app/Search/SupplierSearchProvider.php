<?php

namespace App\Search;

use App\Models\Supplier;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class SupplierSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'suppliers';
    }

    public function labelKey(): string
    {
        return 'resources.suppliers.suppliers';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('suppliers.view')) {
            return collect();
        }

        return Like::any(Supplier::query(), ['name_ar', 'name_en', 'phone', 'email', 'trn'], $term)
            ->orderBy('name_ar')
            ->limit($limit)
            ->get()
            ->map(fn (Supplier $supplier) => new SearchResult($supplier->name, $supplier->type->label().($supplier->phone ? ' · '.$supplier->phone : ''), route('suppliers.show', $supplier)));
    }
}
