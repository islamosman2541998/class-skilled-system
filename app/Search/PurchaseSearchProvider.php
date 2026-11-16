<?php

namespace App\Search;

use App\Models\Purchase;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class PurchaseSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'purchases';
    }

    public function labelKey(): string
    {
        return 'resources.purchases.purchases';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('purchases.view')) {
            return collect();
        }

        return Like::any(Purchase::query()->with('supplier')->visibleTo($user), ['number', 'supplier_invoice_no'], $term)
            ->latest('purchase_date')
            ->limit($limit)
            ->get()
            ->map(fn (Purchase $purchase) => new SearchResult(
                $purchase->number.' · '.$purchase->supplier_invoice_no,
                $purchase->supplier->name.' · '.money($purchase->total),
                route('purchases.show', $purchase),
            ));
    }
}
