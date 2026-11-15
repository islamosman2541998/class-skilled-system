<?php

namespace App\Livewire\Tables\Reports;

use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\Receipt;
use App\Models\ReportRow;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Collections by period and payment method (spec §9.2): active receipts of the period, per day or
 * per month. Cancelled receipts never count.
 */
class CollectionsReport extends ReportTable
{
    protected function aggregate(): QueryBuilder
    {
        $period = ($this->filters['group'] ?? 'month') === 'day' ? 'receipts.receipt_date' : self::monthOf('receipts.receipt_date');

        $query = Receipt::query()->visibleTo(auth()->user())->active()->toBase()
            ->join('lookups', 'lookups.id', '=', 'receipts.payment_method_id')
            ->groupByRaw("{$period}, receipts.payment_method_id, lookups.name_ar, lookups.name_en")
            ->selectRaw("MIN(receipts.id) as id, {$period} as period, receipts.payment_method_id, lookups.name_ar as method_ar, lookups.name_en as method_en")
            ->selectRaw('COUNT(*) as receipts_count, SUM(receipts.amount) as amount');

        return $this->withinPeriod($query, 'receipts.receipt_date');
    }

    public function tableKey(): string
    {
        return 'reports.collections';
    }

    protected function columns(): array
    {
        return [
            Column::make('period', 'reports.period')->sortable()->format(fn (ReportRow $row) => self::showPeriod((string) $row->getAttribute('period')))->onCard(),
            Column::make('method', 'finance.receipts.method')->format(fn (ReportRow $row) => self::localName($row, 'method'))->onCard(),
            Column::make('receipts_count', 'reports.receipts_count')->sortable()->alignEnd()->format(fn (ReportRow $row) => (string) $row->getAttribute('receipts_count')),
            Column::make('amount', 'finance.receipts.amount')->sortable()->summable()->alignEnd()
                ->format(fn (ReportRow $row) => money((string) $row->getAttribute('amount')))->exportAs(fn (ReportRow $row) => $row->getAttribute('amount'))->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            // Read by aggregate() to choose the grouping; it does not filter rows.
            SelectFilter::make('group', 'reports.group_by')
                ->options(fn () => ['month' => __('reports.by_month'), 'day' => __('reports.by_day')])
                ->query(fn (Builder $query) => null),
            SelectFilter::make('payment_method_id', 'finance.receipts.method')->options(fn () => Lookup::options(LookupType::PaymentMethod)->pluck('name', 'id')),
        ];
    }

    protected function dateColumn(): ?string
    {
        return 'period';
    }

    protected function defaultSort(): array
    {
        return ['period', 'desc'];
    }

    /** "2026-11" → 11/2026, "2026-11-16" → 16/11/2026. */
    private static function showPeriod(string $period): string
    {
        $parts = array_reverse(explode('-', substr($period, 0, 10)));

        return implode('/', $parts);
    }
}
