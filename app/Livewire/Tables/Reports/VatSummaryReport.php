<?php

namespace App\Livewire\Tables\Reports;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\ReportRow;
use App\Tables\Column;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * VAT summary per month (spec §9.2): output VAT of issued invoices by invoice date, input VAT of
 * expenses by expense date, and the net. Drafts and cancelled invoices never count.
 */
class VatSummaryReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.vat';
    }

    protected function aggregate(): QueryBuilder
    {
        $user = auth()->user();
        $invoiceMonth = self::monthOf('invoices.issue_date');
        $expenseMonth = self::monthOf('expenses.expense_date');

        $output = $this->withinPeriod(Invoice::query()->visibleTo($user)->active()->toBase()
            ->groupByRaw($invoiceMonth)
            ->selectRaw("{$invoiceMonth} as month, SUM(invoices.subtotal) as sales_net, SUM(invoices.vat_amount) as output_vat, 0 as purchases_net, 0 as input_vat"), 'invoices.issue_date');

        $input = $this->withinPeriod(Expense::query()->visibleTo($user)->toBase()
            ->groupByRaw($expenseMonth)
            ->selectRaw("{$expenseMonth} as month, 0 as sales_net, 0 as output_vat, SUM(expenses.amount) as purchases_net, SUM(expenses.vat_amount) as input_vat"), 'expenses.expense_date');

        return DB::query()->fromSub($output->unionAll($input), 'v')
            ->groupBy('month')
            ->selectRaw('month as id, month, SUM(sales_net) as sales_net, SUM(output_vat) as output_vat, SUM(purchases_net) as purchases_net, SUM(input_vat) as input_vat')
            ->selectRaw('SUM(output_vat) - SUM(input_vat) as net_vat');
    }

    protected function columns(): array
    {
        $money = fn (string $key, string $label) => Column::make($key, $label)->sortable()->summable()->alignEnd()
            ->format(fn (ReportRow $row) => money((string) $row->getAttribute($key)))->exportAs(fn (ReportRow $row) => $row->getAttribute($key));

        return [
            Column::make('month', 'reports.month')->sortable()->format(fn (ReportRow $row) => implode('/', array_reverse(explode('-', (string) $row->getAttribute('month')))))->onCard(),
            $money('sales_net', 'reports.sales_net'),
            $money('output_vat', 'reports.output_vat')->onCard(),
            $money('purchases_net', 'reports.purchases_net'),
            $money('input_vat', 'reports.input_vat')->onCard(),
            $money('net_vat', 'reports.net_vat')->onCard(),
        ];
    }

    protected function dateColumn(): ?string
    {
        return 'month';
    }

    protected function defaultSort(): array
    {
        return ['month', 'desc'];
    }
}
