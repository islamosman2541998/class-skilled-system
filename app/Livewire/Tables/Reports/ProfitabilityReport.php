<?php

namespace App\Livewire\Tables\Reports;

use App\Enums\LookupType;
use App\Enums\ProjectStatus;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\ReportRow;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Project profitability (spec §9.2): contract, invoiced, collected, cost by type (materials,
 * subcontracts, labour, equipment, expenses) and by expense category, profit and margin. The period filters projects by start date. Needs finance.profitability.
 */
class ProfitabilityReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.profitability';
    }

    protected function aggregate(): QueryBuilder
    {
        $query = Project::query()->visibleTo(auth()->user())->toBase()
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->select([
                'projects.id', 'projects.number', 'projects.title_ar', 'projects.title_en', 'projects.status', 'projects.start_date',
                'clients.name_ar as client_ar', 'clients.name_en as client_en',
                'projects.contract_value', 'projects.invoiced_total', 'projects.invoiced_net', 'projects.collected_total', 'projects.cost_total',
            ])
            ->selectRaw('projects.invoiced_net - projects.cost_total as profit')
            ->selectRaw('CASE WHEN projects.invoiced_net = 0 THEN NULL ELSE ROUND((projects.invoiced_net - projects.cost_total) * 100 / projects.invoiced_net, 2) END as margin');

        // Cost by type (spec §9.2): materials, subcontracts, labour, equipment and expenses.
        $sum = fn (string $table, string $column) => DB::table($table)->selectRaw("COALESCE(SUM({$column}), 0)")->whereColumn("{$table}.project_id", 'projects.id');
        $query->selectSub($sum('purchases', 'subtotal')->whereNull('subcontract_id')->whereNull('deleted_at'), 'cost_materials')
            ->selectSub($sum('purchases', 'subtotal')->whereNotNull('subcontract_id')->whereNull('deleted_at'), 'cost_subcontracts')
            ->selectSub($sum('attendances', 'cost'), 'cost_labour')
            ->selectSub($sum('equipment_assignments', 'cost')->whereNull('deleted_at'), 'cost_equipment')
            ->selectSub($sum('expenses', 'amount')->whereNull('deleted_at'), 'cost_expenses');

        foreach ($this->categories() as $category) {
            $query->selectSub(
                DB::table('expenses')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('expenses.project_id', 'projects.id')->where('category_id', $category->id)->whereNull('deleted_at'),
                'cat_'.$category->id,
            );
        }

        return $this->withinPeriod($query, 'projects.start_date');
    }

    protected function columns(): array
    {
        $money = fn (string $key, string $label) => Column::make($key, $label)->sortable()->summable()->alignEnd()
            ->format(fn (ReportRow $row) => money((string) $row->getAttribute($key)))->exportAs(fn (ReportRow $row) => $row->getAttribute($key));

        $columns = [
            Column::make('number', 'projects.number')->sortable()->view('projects.partials.number')->exportAs(fn (ReportRow $row) => (string) $row->getAttribute('number'))->onCard(),
            Column::make('title', 'projects.title')->format(fn (ReportRow $row) => self::localName($row, 'title'))->onCard(),
            Column::make('client', 'projects.client')->format(fn (ReportRow $row) => self::localName($row, 'client'))->hiddenByDefault(),
            $money('contract_value', 'projects.contract_value'),
            $money('invoiced_total', 'projects.invoiced'),
            $money('collected_total', 'projects.collected'),
        ];

        foreach (['materials', 'subcontracts', 'labour', 'equipment', 'expenses'] as $type) {
            $columns[] = $money('cost_'.$type, 'reports.cost_types.'.$type);
        }

        foreach ($this->categories() as $category) {
            $key = 'cat_'.$category->id;
            $columns[] = Column::make($key, 'reports.cost_of')->alignEnd()->hiddenByDefault()
                ->format(fn (ReportRow $row) => money((string) $row->getAttribute($key)))->exportAs(fn (ReportRow $row) => $row->getAttribute($key))
                ->labelWith(['category' => $category->name]);
        }

        return [
            ...$columns,
            $money('cost_total', 'projects.cost'),
            $money('profit', 'projects.profit')->onCard(),
            Column::make('margin', 'projects.margin')->sortable()->alignEnd()
                ->format(fn (ReportRow $row) => $row->getAttribute('margin') !== null ? rtrim(rtrim(number_format((float) $row->getAttribute('margin'), 2, '.', ''), '0'), '.').'%' : '')
                ->exportAs(fn (ReportRow $row) => $row->getAttribute('margin'))->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('status', 'projects.status')
                ->options(fn () => collect(ProjectStatus::cases())->mapWithKeys(fn (ProjectStatus $status) => [$status->value => $status->label()])),
        ];
    }

    protected function searchColumns(): array
    {
        return ['number', 'title_ar', 'title_en', 'client_ar', 'client_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'start_date';
    }

    /**
     * @return Collection<int, Lookup>
     */
    private function categories(): Collection
    {
        return Lookup::options(LookupType::ExpenseCategory);
    }
}
