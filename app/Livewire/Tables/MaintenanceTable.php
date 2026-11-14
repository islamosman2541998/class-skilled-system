<?php

namespace App\Livewire\Tables;

use App\Enums\MaintenanceStatus;
use App\Livewire\DataTable;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaintenanceTable extends DataTable
{
    public function tableKey(): string
    {
        return 'maintenance';
    }

    protected function permissionPrefix(): ?string
    {
        return 'maintenance';
    }

    protected function query(): Builder
    {
        return MaintenanceRequest::query()->with(['project', 'assignee']);
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'maintenance.number')->sortable()->view('maintenance.partials.number')->exportAs(fn (MaintenanceRequest $row) => $row->number)->onCard(),
            Column::make('project', 'maintenance.project')->format(fn (MaintenanceRequest $row) => $row->project->title)->onCard(),
            Column::make('request_date', 'maintenance.request_date')->sortable()->format(fn (MaintenanceRequest $row) => fmt_date($row->request_date))->onCard(),
            Column::make('description', 'maintenance.description')->format(fn (MaintenanceRequest $row) => Str::limit($row->description, 80))->exportAs(fn (MaintenanceRequest $row) => $row->description),
            Column::make('in_warranty', 'maintenance.warranty')->sortable()->view('maintenance.partials.warranty')
                ->exportAs(fn (MaintenanceRequest $row) => $row->in_warranty ? __('maintenance.in_warranty') : __('maintenance.out_of_warranty')),
            Column::make('status', 'maintenance.status')->sortable()->view('maintenance.partials.status')->exportAs(fn (MaintenanceRequest $row) => $row->status->label())->onCard(),
            Column::make('assigned_to', 'maintenance.assigned_to')->format(fn (MaintenanceRequest $row) => (string) $row->assignee?->name)->hiddenByDefault(),
            Column::make('cost', 'maintenance.cost')->sortable()->summable()->alignEnd()
                ->format(fn (MaintenanceRequest $row) => money($row->cost))->exportAs(fn (MaintenanceRequest $row) => $row->cost),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('status', 'maintenance.status')
                ->options(fn () => collect(MaintenanceStatus::cases())->mapWithKeys(fn (MaintenanceStatus $status) => [$status->value => $status->label()])),
            SelectFilter::make('project_id', 'maintenance.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])),
            SelectFilter::make('assigned_to', 'maintenance.assigned_to')->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            BooleanFilter::make('in_warranty', 'maintenance.in_warranty'),
        ];
    }

    protected function searchColumns(): array
    {
        return ['number', 'description', 'project.title_ar', 'project.title_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'request_date';
    }

    protected function defaultSort(): array
    {
        return ['request_date', 'desc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('maintenance.update') ? route('maintenance.edit', $row) : null;
    }
}
