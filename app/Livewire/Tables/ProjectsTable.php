<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Enums\ProjectStatus;
use App\Livewire\DataTable;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\NumberRangeFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class ProjectsTable extends DataTable
{
    #[Locked]
    public ?int $clientId = null;

    public function tableKey(): string
    {
        return $this->clientId ? 'projects.client' : 'projects';
    }

    protected function permissionPrefix(): ?string
    {
        return 'projects';
    }

    protected function query(): Builder
    {
        return Project::query()
            ->with(['client', 'manager'])
            ->when($this->clientId, fn (Builder $query) => $query->where('client_id', $this->clientId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'projects.number')->sortable()->view('projects.partials.number')->exportAs(fn (Project $row) => $row->number)->onCard(),
            Column::make('title_ar', 'projects.title')->sortable()->format(fn (Project $row) => $row->title)->onCard(),
            Column::make('client', 'projects.client')->format(fn (Project $row) => $row->client->name),
            Column::make('manager_id', 'projects.manager')->format(fn (Project $row) => $row->manager->name)->hiddenByDefault(),
            Column::make('status', 'projects.status')->sortable()->view('projects.partials.status')->exportAs(fn (Project $row) => $row->status->label())->onCard(),
            Column::make('progress', 'projects.progress')->sortable()->view('projects.partials.progress')->exportAs(fn (Project $row) => $row->progress)->onCard(),
            Column::make('planned_end_date', 'projects.planned_end_date')->sortable()->view('projects.partials.end-date')
                ->exportAs(fn (Project $row) => $row->planned_end_date ? fmt_date($row->planned_end_date) : ''),
            Column::make('contract_value', 'projects.contract_value')->sortable()->summable()->alignEnd()
                ->format(fn (Project $row) => money($row->contract_value))->exportAs(fn (Project $row) => $row->contract_value),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('status', 'projects.status')
                ->options(fn () => collect(ProjectStatus::cases())->mapWithKeys(fn (ProjectStatus $status) => [$status->value => $status->label()])),
            SelectFilter::make('service_id', 'projects.service')->options(fn () => Lookup::options(LookupType::Service)->pluck('name', 'id')),
            SelectFilter::make('manager_id', 'projects.manager')->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            SelectFilter::make('emirate_id', 'projects.emirate')->options(fn () => Lookup::options(LookupType::Emirate)->pluck('name', 'id')),
            BooleanFilter::make('late', 'projects.late')->query(function (Builder $query, mixed $value) {
                if ((string) $value !== '1') {
                    return;
                }

                $closed = array_map(fn (ProjectStatus $status) => $status->value, array_filter(ProjectStatus::cases(), fn (ProjectStatus $status) => $status->isClosed()));
                $query->whereNotNull('planned_end_date')->whereDate('planned_end_date', '<', today())->whereNotIn('status', $closed);
            }),
            NumberRangeFilter::make('contract_value', 'projects.contract_value'),
        ];
    }

    protected function searchColumns(): array
    {
        return ['number', 'title_ar', 'title_en', 'location', 'client.name_ar', 'client.name_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'start_date';
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('projects.update') ? route('projects.edit', $row) : null;
    }
}
