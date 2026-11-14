<?php

namespace App\Livewire\Tables;

use App\Enums\InvoiceStatus;
use App\Livewire\DataTable;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\DateRangeFilter;
use App\Tables\Filters\NumberRangeFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class InvoicesTable extends DataTable
{
    #[Locked]
    public ?int $projectId = null;

    #[Locked]
    public ?int $clientId = null;

    public function tableKey(): string
    {
        return match (true) {
            $this->projectId !== null => 'invoices.project',
            $this->clientId !== null => 'invoices.client',
            default => 'invoices',
        };
    }

    protected function permissionPrefix(): ?string
    {
        return 'invoices';
    }

    protected function query(): Builder
    {
        return Invoice::query()
            ->with(['client', 'project'])
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId))
            ->when($this->clientId, fn (Builder $query) => $query->where('client_id', $this->clientId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'finance.number')->sortable()->view('invoices.partials.number')->exportAs(fn (Invoice $row) => (string) $row->number)->onCard(),
            Column::make('client', 'finance.client')->format(fn (Invoice $row) => $row->client->name)->onCard(),
            Column::make('project', 'finance.project')->format(fn (Invoice $row) => $row->project->number.' · '.$row->project->title),
            Column::make('issue_date', 'finance.invoices.issue_date')->sortable()->format(fn (Invoice $row) => fmt_date($row->issue_date)),
            Column::make('due_date', 'finance.invoices.due_date')->sortable()->view('invoices.partials.due-date')->exportAs(fn (Invoice $row) => fmt_date($row->due_date))->onCard(),
            Column::make('total', 'finance.total')->sortable()->summable()->alignEnd()->format(fn (Invoice $row) => money($row->total))->exportAs(fn (Invoice $row) => $row->total)->onCard(),
            Column::make('paid_amount', 'finance.invoices.paid')->sortable()->summable()->alignEnd()->format(fn (Invoice $row) => money($row->paid_amount))->exportAs(fn (Invoice $row) => $row->paid_amount)->hiddenByDefault(),
            Column::make('remaining', 'finance.invoices.remaining')->alignEnd()->format(fn (Invoice $row) => money($row->remaining()))->exportAs(fn (Invoice $row) => $row->remaining()),
            Column::make('status', 'finance.status')->sortable()->view('invoices.partials.status')->exportAs(fn (Invoice $row) => $row->status->label())->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            SelectFilter::make('status', 'finance.status')
                ->options(fn () => collect(InvoiceStatus::cases())->mapWithKeys(fn (InvoiceStatus $status) => [$status->value => $status->label()])),
            BooleanFilter::make('overdue', 'finance.invoices.overdue')->query(function (Builder $query, mixed $value) {
                (string) $value === '1'
                    ? Invoice::constrainOverdue($query)
                    : $query->whereNot(fn (Builder $inner) => Invoice::constrainOverdue($inner));
            }),
            $this->projectId === null ? SelectFilter::make('project_id', 'finance.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])) : null,
            $this->clientId === null ? SelectFilter::make('client_id', 'finance.client')
                ->options(fn () => Client::query()->visibleTo(auth()->user())->whereHas('invoices')->orderBy('name_ar')->get()->mapWithKeys(fn (Client $client) => [$client->id => $client->name])) : null,
            NumberRangeFilter::make('total', 'finance.total'),
            DateRangeFilter::make('due_date', 'finance.invoices.due_date'),
        ]));
    }

    protected function searchColumns(): array
    {
        return ['number', 'client.name_ar', 'client.name_en', 'project.number', 'project.title_ar', 'project.title_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'issue_date';
    }

    protected function defaultSort(): array
    {
        return ['issue_date', 'desc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return $row instanceof Invoice && $row->isDraft() && auth()->user()?->can('invoices.update') ? route('invoices.edit', $row) : null;
    }
}
