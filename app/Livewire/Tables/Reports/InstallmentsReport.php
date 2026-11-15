<?php

namespace App\Livewire\Tables\Reports;

use App\Enums\InstallmentStatus;
use App\Models\Project;
use App\Models\ProjectInstallment;
use App\Models\ReportRow;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Project installments across the projects the user may see (spec §8.8, §9.1): the list behind the
 * "due installments" figure. The period applies to the installment due date.
 */
class InstallmentsReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.installments';
    }

    protected function aggregate(): QueryBuilder
    {
        $query = ProjectInstallment::query()->toBase()
            ->join('projects', 'projects.id', '=', 'project_installments.project_id')
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->whereIn('project_installments.project_id', Project::query()->visibleTo(auth()->user())->select('projects.id'))
            ->select([
                'project_installments.id', 'project_installments.project_id', 'project_installments.label_ar', 'project_installments.label_en',
                'project_installments.amount', 'project_installments.due_date', 'project_installments.status',
                'projects.number', 'projects.title_ar', 'projects.title_en', 'clients.name_ar as client_ar', 'clients.name_en as client_en',
            ]);

        return $this->withinPeriod($query, 'project_installments.due_date');
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'projects.number')->sortable()->view('reports.partials.installment-project')
                ->exportAs(fn (ReportRow $row) => (string) $row->getAttribute('number'))->onCard(),
            Column::make('title', 'projects.title')->format(fn (ReportRow $row) => self::localName($row, 'title')),
            Column::make('client', 'finance.client')->format(fn (ReportRow $row) => self::localName($row, 'client')),
            Column::make('label', 'reports.installment')->format(fn (ReportRow $row) => self::localName($row, 'label'))->onCard(),
            Column::make('amount', 'projects.installments.amount')->sortable()->summable()->alignEnd()
                ->format(fn (ReportRow $row) => money((string) $row->getAttribute('amount')))->exportAs(fn (ReportRow $row) => $row->getAttribute('amount'))->onCard(),
            Column::make('due_date', 'projects.installments.due_date')->sortable()
                ->format(fn (ReportRow $row) => $row->getAttribute('due_date') ? implode('/', array_reverse(explode('-', substr((string) $row->getAttribute('due_date'), 0, 10)))) : ''),
            Column::make('status', 'reports.status')->sortable()->view('reports.partials.installment-status')
                ->exportAs(fn (ReportRow $row) => InstallmentStatus::from((string) $row->getAttribute('status'))->label())->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('status', 'reports.status')
                ->options(fn () => collect(InstallmentStatus::cases())->mapWithKeys(fn (InstallmentStatus $status) => [$status->value => $status->label()])),
        ];
    }

    protected function searchColumns(): array
    {
        return ['number', 'title_ar', 'title_en', 'label_ar', 'label_en', 'client_ar', 'client_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'due_date';
    }

    protected function defaultSort(): array
    {
        return ['due_date', 'asc'];
    }
}
