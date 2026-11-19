<?php

namespace App\Services\Dashboard;

use App\Enums\LookupType;
use App\Enums\ProjectStatus;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard pieces beyond single figures (spec §9.1).
 */
class Widgets
{
    /** @var array<string, list<string>> */
    private const PERMISSIONS = [
        'revenue_cost' => ['finance.profitability'],
        'project_status' => ['projects.view'],
        'sales_pipeline' => ['leads.view'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::PERMISSIONS);
    }

    /**
     * Charts the user is allowed to add to their dashboard, without running their data queries.
     *
     * @return array<string, array{key: string, title: string, description: string}>
     */
    public static function available(User $user): array
    {
        $widgets = [];

        foreach (self::PERMISSIONS as $key => $permissions) {
            if (! collect($permissions)->every(fn (string $permission) => $user->can($permission))) {
                continue;
            }

            $widgets[$key] = [
                'key' => $key,
                'title' => __('home.charts.'.$key.'.title'),
                'description' => __('home.charts.'.$key.'.description'),
            ];
        }

        return $widgets;
    }

    /**
     * Visible dashboard charts. Hidden charts are deliberately not queried.
     *
     * @param  list<string>  $hidden
     * @return array<string, array{key: string, title: string, description: string, type: string, data: array}>
     */
    public static function for(User $user, Period $period, array $hidden = []): array
    {
        $widgets = [];

        foreach (self::available($user) as $key => $meta) {
            if (in_array($key, $hidden, true)) {
                continue;
            }

            [$type, $data] = match ($key) {
                'revenue_cost' => ['bar', self::revenueVsCost($user, $period)],
                'project_status' => ['donut', self::projectStatus($user)],
                'sales_pipeline' => ['horizontal', self::salesPipeline($user, $period)],
            };

            $widgets[$key] = [...$meta, 'type' => $type, 'data' => $data];
        }

        return $widgets;
    }

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

    /** @return list<array{label: string, value: int, color: string}> */
    private static function projectStatus(User $user): array
    {
        $counts = Project::query()->visibleTo($user)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $colors = [
            'not_started' => '#8A96A8',
            'in_progress' => '#2A78D6',
            'on_hold' => '#E3A008',
            'completed' => '#1F9D6A',
            'delivered' => '#4FC3EE',
            'cancelled' => '#D9544D',
        ];

        return array_map(fn (ProjectStatus $status) => [
            'label' => $status->label(),
            'value' => (int) ($counts[$status->value] ?? 0),
            'color' => $colors[$status->value],
        ], ProjectStatus::cases());
    }

    /** @return list<array{label: string, value: int, color: string}> */
    private static function salesPipeline(User $user, Period $period): array
    {
        $counts = Lead::query()->visibleTo($user)
            ->whereBetween('created_at', [$period->from->copy()->startOfDay(), $period->to->copy()->endOfDay()])
            ->selectRaw('status_id, COUNT(*) as aggregate')
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');
        $colors = [
            'success' => '#1F9D6A',
            'warning' => '#E3A008',
            'danger' => '#D9544D',
            'info' => '#2A78D6',
            'neutral' => '#8A96A8',
        ];

        return Lookup::query()->ofType(LookupType::LeadStatus)->active()
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Lookup $status) => [
                'label' => $status->name,
                'value' => (int) ($counts[$status->id] ?? 0),
                'color' => $colors[$status->color ?? 'neutral'] ?? $colors['neutral'],
            ])->all();
    }
}
