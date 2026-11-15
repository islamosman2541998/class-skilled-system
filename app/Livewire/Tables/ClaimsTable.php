<?php

namespace App\Livewire\Tables;

use App\Enums\ClaimStatus;
use App\Livewire\DataTable;
use App\Models\Claim;
use App\Models\Client;
use App\Models\Project;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * Progress claims (spec §8.14): filters status, project, client and the period end.
 */
class ClaimsTable extends DataTable
{
    #[Locked]
    public ?int $projectId = null;

    public function tableKey(): string
    {
        return $this->projectId !== null ? 'claims.project' : 'claims';
    }

    protected function permissionPrefix(): ?string
    {
        return 'claims';
    }

    protected function query(): Builder
    {
        return Claim::query()
            ->with(['client', 'project'])
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'site.claims.number')->sortable()->view('claims.partials.number')->exportAs(fn (Claim $row) => (string) $row->number)->onCard(),
            Column::make('project', 'site.claims.project')->format(fn (Claim $row) => $row->project->number.' · '.$row->project->title),
            Column::make('client', 'site.claims.client')->format(fn (Claim $row) => $row->client->name)->onCard(),
            Column::make('period_to', 'site.claims.period')->sortable()
                ->format(fn (Claim $row) => fmt_date($row->period_from).' - '.fmt_date($row->period_to))->onCard(),
            Column::make('net_amount', 'site.claims.net')->sortable()->summable()->alignEnd()->format(fn (Claim $row) => money($row->net_amount))->exportAs(fn (Claim $row) => $row->net_amount),
            Column::make('total', 'site.claims.total')->sortable()->summable()->alignEnd()->format(fn (Claim $row) => money($row->total))->exportAs(fn (Claim $row) => $row->total)->onCard(),
            Column::make('status', 'site.claims.status')->sortable()->view('claims.partials.status')->exportAs(fn (Claim $row) => $row->status->label())->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            SelectFilter::make('status', 'site.claims.status')
                ->options(fn () => collect(ClaimStatus::cases())->mapWithKeys(fn (ClaimStatus $status) => [$status->value => $status->label()])),
            $this->projectId === null ? SelectFilter::make('project_id', 'site.claims.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->where('billing_method', 'claims')->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])) : null,
            $this->projectId === null ? SelectFilter::make('client_id', 'site.claims.client')
                ->options(fn () => Client::query()->visibleTo(auth()->user())->whereIn('id', Claim::query()->select('client_id'))->orderBy('name_ar')->get()->mapWithKeys(fn (Client $client) => [$client->id => $client->name])) : null,
        ]));
    }

    protected function searchColumns(): array
    {
        return ['number', 'client.name_ar', 'client.name_en', 'project.number', 'project.title_ar', 'project.title_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'period_to';
    }

    protected function defaultSort(): array
    {
        return ['period_to', 'desc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return $row instanceof Claim && $row->isDraft() && auth()->user()?->can('claims.update') ? route('claims.edit', $row) : null;
    }
}
