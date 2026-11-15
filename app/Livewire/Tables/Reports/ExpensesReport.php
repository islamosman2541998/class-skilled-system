<?php

namespace App\Livewire\Tables\Reports;

use App\Enums\LookupType;
use App\Models\Expense;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\ReportRow;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Expenses by category and project in the period (spec §9.2); a general expense has no project.
 * Needs finance.costs.
 */
class ExpensesReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.expenses';
    }

    protected function aggregate(): QueryBuilder
    {
        $query = Expense::query()->visibleTo(auth()->user())->toBase()
            ->join('lookups', 'lookups.id', '=', 'expenses.category_id')
            ->leftJoin('projects', 'projects.id', '=', 'expenses.project_id')
            ->groupBy('expenses.category_id', 'lookups.name_ar', 'lookups.name_en', 'expenses.project_id', 'projects.number', 'projects.title_ar', 'projects.title_en')
            ->selectRaw('MIN(expenses.id) as id, expenses.category_id, lookups.name_ar as category_ar, lookups.name_en as category_en')
            ->selectRaw('expenses.project_id, projects.number as project_number, projects.title_ar, projects.title_en')
            ->selectRaw('COUNT(*) as expenses_count, SUM(expenses.amount) as amount, SUM(expenses.vat_amount) as vat_amount, SUM(expenses.total) as total');

        return $this->withinPeriod($query, 'expenses.expense_date');
    }

    protected function columns(): array
    {
        $money = fn (string $key, string $label) => Column::make($key, $label)->sortable()->summable()->alignEnd()
            ->format(fn (ReportRow $row) => money((string) $row->getAttribute($key)))->exportAs(fn (ReportRow $row) => $row->getAttribute($key));

        return [
            Column::make('category', 'finance.expenses.category')->format(fn (ReportRow $row) => self::localName($row, 'category'))->onCard(),
            Column::make('project', 'finance.project')->format(fn (ReportRow $row) => $row->getAttribute('project_id')
                ? $row->getAttribute('project_number').' · '.self::localName($row, 'title')
                : __('finance.expenses.general'))->onCard(),
            Column::make('expenses_count', 'reports.expenses_count')->sortable()->alignEnd()->format(fn (ReportRow $row) => (string) $row->getAttribute('expenses_count')),
            $money('amount', 'finance.expenses.amount')->onCard(),
            $money('vat_amount', 'finance.vat'),
            $money('total', 'finance.total'),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('category_id', 'finance.expenses.category')->options(fn () => Lookup::options(LookupType::ExpenseCategory)->pluck('name', 'id')),
            SelectFilter::make('project_id', 'finance.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])),
        ];
    }

    protected function dateColumn(): ?string
    {
        return 'period';
    }

    protected function defaultSort(): array
    {
        return ['amount', 'desc'];
    }
}
