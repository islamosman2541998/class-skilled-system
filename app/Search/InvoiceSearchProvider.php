<?php

namespace App\Search;

use App\Models\Invoice;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class InvoiceSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'invoices';
    }

    public function labelKey(): string
    {
        return 'finance.invoices.invoices';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('invoices.view')) {
            return collect();
        }

        return Like::any(Invoice::query()->with(['client', 'project'])->visibleTo($user), ['number', 'client.name_ar', 'client.name_en', 'project.number', 'project.title_ar'], $term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice) => new SearchResult(
                ($invoice->number ?? __('finance.invoices.statuses.draft')).' · '.$invoice->client->name,
                money($invoice->total).' · '.$invoice->status->label(),
                route('invoices.show', $invoice),
            ));
    }
}
