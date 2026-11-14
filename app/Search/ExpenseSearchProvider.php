<?php

namespace App\Search;

use App\Models\Expense;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class ExpenseSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'expenses';
    }

    public function labelKey(): string
    {
        return 'finance.expenses.expenses';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('expenses.view')) {
            return collect();
        }

        return Like::any(Expense::query()->with(['category', 'project'])->visibleTo($user), ['number', 'supplier_name', 'supplier_invoice_no', 'description'], $term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Expense $expense) => new SearchResult(
                $expense->number.' · '.$expense->category->name,
                money($expense->total).' · '.($expense->project->number ?? __('finance.expenses.general')),
                route('expenses.show', $expense),
            ));
    }
}
