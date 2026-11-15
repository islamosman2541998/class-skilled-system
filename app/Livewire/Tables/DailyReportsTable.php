<?php

namespace App\Livewire\Tables;

use App\Livewire\DataTable;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

/**
 * Daily reports (spec §8.13): filters project, engineer, has obstacles and the report date.
 */
class DailyReportsTable extends DataTable
{
    #[Locked]
    public ?int $projectId = null;

    public function tableKey(): string
    {
        return $this->projectId !== null ? 'daily_reports.project' : 'daily_reports';
    }

    protected function permissionPrefix(): ?string
    {
        return 'daily_reports';
    }

    protected function query(): Builder
    {
        return DailyReport::query()
            ->with(['project', 'engineer'])
            ->withCount('stages')
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId));
    }

    protected function columns(): array
    {
        return [
            Column::make('report_date', 'site.reports.date')->sortable()->view('daily-reports.partials.date')->exportAs(fn (DailyReport $row) => fmt_date($row->report_date))->onCard(),
            Column::make('project', 'site.reports.project')->format(fn (DailyReport $row) => $row->project->number.' · '.$row->project->title)->onCard(),
            Column::make('engineer_id', 'site.reports.engineer')->format(fn (DailyReport $row) => (string) $row->engineer?->name)->onCard(),
            Column::make('labour_count', 'site.reports.labour_count')->sortable()->alignEnd()->format(fn (DailyReport $row) => (string) $row->labour_count),
            Column::make('stages_count', 'site.reports.stages_count')->alignEnd()->format(fn (DailyReport $row) => (string) $row->getAttribute('stages_count')),
            Column::make('has_obstacles', 'site.reports.obstacles')->sortable()
                ->format(fn (DailyReport $row) => $row->has_obstacles ? __('site.reports.has_obstacles') : '')->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            $this->projectId === null ? SelectFilter::make('project_id', 'site.reports.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])) : null,
            SelectFilter::make('engineer_id', 'site.reports.engineer')
                ->options(fn () => User::query()->whereIn('id', DailyReport::query()->visibleTo(auth()->user())->select('engineer_id'))->orderBy('name')->pluck('name', 'id')),
            BooleanFilter::make('has_obstacles', 'site.reports.has_obstacles'),
        ]));
    }

    protected function searchColumns(): array
    {
        return ['work_done', 'obstacles', 'project.number', 'project.title_ar', 'project.title_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'report_date';
    }

    protected function defaultSort(): array
    {
        return ['report_date', 'desc'];
    }
}
