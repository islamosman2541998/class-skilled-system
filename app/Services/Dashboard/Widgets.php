<?php

namespace App\Services\Dashboard;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard pieces beyond single figures (spec §9.1).
 */
class Widgets
{
    /**
     * Revenue (issued invoices before VAT, by issue date) against cost (expenses before VAT, by
     * expense date) for the six months ending with the period's last month.
     *
     * @return list<array{month: string, label: string, short: string, revenue: string, cost: string}>
     */
    public static function revenueVsCost(User $user, Period $period, int $months = 6): array
    {
        $end = $period->to->copy()->startOfMonth();
        $start = $end->copy()->subMonths($months - 1);
        $last = $end->copy()->endOfMonth();

        $month = fn (string $column) => DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";

        $revenue = Invoice::query()->visibleTo($user)->active()
            ->whereDate('invoices.issue_date', '>=', $start->toDateString())->whereDate('invoices.issue_date', '<=', $last->toDateString())
            ->toBase()->groupByRaw($month('invoices.issue_date'))
            ->selectRaw("{$month('invoices.issue_date')} as month, SUM(invoices.subtotal) as amount")
            ->pluck('amount', 'month');
        $cost = Expense::query()->visibleTo($user)
            ->whereDate('expenses.expense_date', '>=', $start->toDateString())->whereDate('expenses.expense_date', '<=', $last->toDateString())
            ->toBase()->groupByRaw($month('expenses.expense_date'))
            ->selectRaw("{$month('expenses.expense_date')} as month, SUM(expenses.amount) as amount")
            ->pluck('amount', 'month');

        $rows = [];

        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $rows[] = [
                'month' => $key,
                'label' => $start->copy()->addMonths($i)->format('m/Y'),
                'short' => $start->copy()->addMonths($i)->format('m/y'),
                'revenue' => bcadd((string) ($revenue[$key] ?? '0'), '0', 2),
                'cost' => bcadd((string) ($cost[$key] ?? '0'), '0', 2),
            ];
        }

        return $rows;
    }
}
