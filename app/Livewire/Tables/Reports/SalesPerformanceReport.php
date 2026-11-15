<?php

namespace App\Livewire\Tables\Reports;

use App\Models\Lead;
use App\Models\Quotation;
use App\Models\ReportRow;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Sales performance (spec §9.2): leads created in the period and quotations issued in it, grouped by
 * lead source, service or assignee, with quotations by status and the conversion rate
 * accepted ÷ (sent + accepted + rejected). A superseded revision does not count; its latest revision does.
 */
class SalesPerformanceReport extends ReportTable
{
    /** Every quotation status, one count column each. */
    private const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    public function tableKey(): string
    {
        return 'reports.sales';
    }

    private function group(): string
    {
        $group = (string) ($this->filters['group'] ?? '');

        return in_array($group, ['service', 'assignee'], true) ? $group : 'source';
    }

    protected function aggregate(): QueryBuilder
    {
        $user = auth()->user();
        $group = $this->group();
        [$leadKey, $quoteKey] = match ($group) {
            'service' => ['leads.service_id', 'quotations.service_id'],
            'assignee' => ['leads.assigned_to', 'quotations.assigned_to'],
            default => ['leads.source_id', 'quotation_leads.source_id'],
        };
        $zeros = collect(self::STATUSES)->map(fn (string $status) => "0 as is_{$status}")->implode(', ');
        $flags = collect(self::STATUSES)
            ->map(fn (string $status) => "CASE WHEN quotations.status = '{$status}' THEN 1 ELSE 0 END as is_{$status}")
            ->implode(', ');

        $leads = Lead::query()->visibleTo($user)->toBase()
            ->selectRaw("COALESCE({$leadKey}, 0) as dim, 1 as is_lead, 0 as is_quotation, {$zeros}, 0 as accepted_value");
        $quotations = Quotation::query()->visibleTo($user)->whereNull('quotations.superseded_at')->toBase()
            ->when($group === 'source', fn (QueryBuilder $q) => $q->leftJoin('leads as quotation_leads', 'quotation_leads.id', '=', 'quotations.lead_id'))
            ->selectRaw("COALESCE({$quoteKey}, 0) as dim, 0 as is_lead, 1 as is_quotation, {$flags}, CASE WHEN quotations.status = 'accepted' THEN quotations.total ELSE 0 END as accepted_value");

        $this->withinPeriod($leads, 'leads.created_at');
        $this->withinPeriod($quotations, 'quotations.issue_date');

        $decided = 'SUM(u.is_sent) + SUM(u.is_accepted) + SUM(u.is_rejected)';
        $query = DB::query()->fromSub($leads->unionAll($quotations), 'u')
            ->selectRaw('u.dim as id, SUM(u.is_lead) as leads_count, SUM(u.is_quotation) as quotations_count, SUM(u.accepted_value) as accepted_value')
            ->selectRaw("CASE WHEN {$decided} > 0 THEN ROUND(SUM(u.is_accepted) * 100.0 / ({$decided}), 2) END as conversion_rate");

        foreach (self::STATUSES as $status) {
            $query->selectRaw("SUM(u.is_{$status}) as {$status}_count");
        }

        if ($group === 'assignee') {
            return $query->leftJoin('users as n', 'n.id', '=', 'u.dim')
                ->selectRaw('n.name as name_ar, NULL as name_en')
                ->groupBy('u.dim', 'n.name');
        }

        return $query->leftJoin('lookups as n', 'n.id', '=', 'u.dim')
            ->selectRaw('n.name_ar as name_ar, n.name_en as name_en')
            ->groupBy('u.dim', 'n.name_ar', 'n.name_en');
    }

    protected function columns(): array
    {
        $count = fn (string $key, string $label) => Column::make($key, $label)->sortable()->alignEnd()
            ->format(fn (ReportRow $row) => (string) (int) $row->getAttribute($key));

        $statusColumns = collect(self::STATUSES)
            ->map(fn (string $status) => $count($status.'_count', 'quotations.statuses.'.$status))
            ->all();

        return [
            Column::make('name', 'reports.sales_'.$this->group())->format(fn (ReportRow $row) => self::localName($row, 'name') ?: __('reports.unspecified'))->onCard(),
            $count('leads_count', 'reports.leads_count')->onCard(),
            $count('quotations_count', 'reports.quotations_count')->onCard(),
            ...$statusColumns,
            Column::make('accepted_value', 'reports.accepted_value')->sortable()->summable()->alignEnd()
                ->format(fn (ReportRow $row) => money((string) $row->getAttribute('accepted_value')))->exportAs(fn (ReportRow $row) => $row->getAttribute('accepted_value')),
            Column::make('conversion_rate', 'reports.conversion_rate')->sortable()->alignEnd()
                ->format(fn (ReportRow $row) => $row->getAttribute('conversion_rate') === null ? '' : number_format((float) $row->getAttribute('conversion_rate'), 2).'%')
                ->exportAs(fn (ReportRow $row) => $row->getAttribute('conversion_rate'))->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            // Read by aggregate() to choose the grouping; it does not filter rows.
            SelectFilter::make('group', 'reports.group_by')
                ->options(fn () => ['source' => __('reports.sales_source'), 'service' => __('reports.sales_service'), 'assignee' => __('reports.sales_assignee')])
                ->query(fn (Builder $query) => null),
        ];
    }

    protected function searchColumns(): array
    {
        return ['name_ar', 'name_en'];
    }

    /** Shows the period picker; the period applies to lead and quotation dates inside the aggregate. */
    protected function dateColumn(): ?string
    {
        return 'id';
    }

    protected function defaultSort(): array
    {
        return ['leads_count', 'desc'];
    }
}
