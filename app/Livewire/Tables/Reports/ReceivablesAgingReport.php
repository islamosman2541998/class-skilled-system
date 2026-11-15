<?php

namespace App\Livewire\Tables\Reports;

use App\Models\Invoice;
use App\Models\ReportRow;
use App\Tables\Column;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Receivables aging (spec §9.2): what each client owes on issued invoices today, split by days past
 * the due date — current, 1–30, 31–60, 61–90 and over 90.
 */
class ReceivablesAgingReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.aging';
    }

    protected function aggregate(): QueryBuilder
    {
        $today = today();
        $open = 'invoices.total - invoices.paid_amount';
        $bucket = fn (string $alias, ?int $from, ?int $to) => sprintf(
            'SUM(CASE WHEN %s THEN %s ELSE 0 END) as %s',
            match (true) {
                $from === null => 'invoices.due_date >= ?',
                $to === null => 'invoices.due_date <= ?',
                default => 'invoices.due_date BETWEEN ? AND ?',
            },
            $open,
            $alias,
        );

        return Invoice::query()->visibleTo(auth()->user())->active()->toBase()
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereColumn('invoices.paid_amount', '<', 'invoices.total')
            ->groupBy('invoices.client_id', 'clients.name_ar', 'clients.name_en')
            ->select(['invoices.client_id as id', 'clients.name_ar as client_ar', 'clients.name_en as client_en'])
            ->selectRaw("COUNT(*) as invoices_count, SUM({$open}) as outstanding")
            ->selectRaw($bucket('current_amount', null, 0), [$today->toDateString()])
            ->selectRaw($bucket('days_1_30', 1, 30), [$today->copy()->subDays(30)->toDateString(), $today->copy()->subDay()->toDateString()])
            ->selectRaw($bucket('days_31_60', 31, 60), [$today->copy()->subDays(60)->toDateString(), $today->copy()->subDays(31)->toDateString()])
            ->selectRaw($bucket('days_61_90', 61, 90), [$today->copy()->subDays(90)->toDateString(), $today->copy()->subDays(61)->toDateString()])
            ->selectRaw($bucket('days_over_90', 91, null), [$today->copy()->subDays(91)->toDateString()]);
    }

    protected function columns(): array
    {
        $money = fn (string $key, string $label) => Column::make($key, $label)->sortable()->summable()->alignEnd()
            ->format(fn (ReportRow $row) => money((string) $row->getAttribute($key)))->exportAs(fn (ReportRow $row) => $row->getAttribute($key));

        return [
            Column::make('client', 'finance.client')->format(fn (ReportRow $row) => self::localName($row, 'client'))->onCard(),
            Column::make('invoices_count', 'reports.invoices_count')->sortable()->alignEnd()->format(fn (ReportRow $row) => (string) $row->getAttribute('invoices_count')),
            $money('current_amount', 'reports.aging_current'),
            $money('days_1_30', 'reports.aging_1_30'),
            $money('days_31_60', 'reports.aging_31_60'),
            $money('days_61_90', 'reports.aging_61_90'),
            $money('days_over_90', 'reports.aging_over_90'),
            $money('outstanding', 'reports.outstanding')->onCard(),
        ];
    }

    protected function searchColumns(): array
    {
        return ['client_ar', 'client_en'];
    }

    /** Aging is always as of today. */
    protected function dateColumn(): ?string
    {
        return null;
    }

    protected function defaultSort(): array
    {
        return ['outstanding', 'desc'];
    }
}
