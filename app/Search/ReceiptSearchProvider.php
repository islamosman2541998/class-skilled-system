<?php

namespace App\Search;

use App\Models\Receipt;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class ReceiptSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'receipts';
    }

    public function labelKey(): string
    {
        return 'finance.receipts.receipts';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('receipts.view')) {
            return collect();
        }

        return Like::any(Receipt::query()->with('client')->visibleTo($user), ['number', 'reference', 'client.name_ar', 'client.name_en'], $term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Receipt $receipt) => new SearchResult($receipt->number.' · '.$receipt->client->name, money($receipt->amount).' · '.fmt_date($receipt->receipt_date), route('receipts.show', $receipt)));
    }
}
