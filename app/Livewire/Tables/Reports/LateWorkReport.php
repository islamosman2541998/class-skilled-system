<?php

namespace App\Livewire\Tables\Reports;

use App\Enums\ProjectStatus;
use App\Enums\StageStatus;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ReportRow;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Late projects and stages (spec §9.2), as of today: open projects past their planned end and
 * unfinished stages past theirs, in projects that are still open.
 */
class LateWorkReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.late';
    }

    protected function aggregate(): QueryBuilder
    {
        $user = auth()->user();
        $today = today()->toDateString();
        $closed = collect(ProjectStatus::cases())->filter(fn (ProjectStatus $status) => $status->isClosed())->map->value->values()->all();
        $daysLate = fn (string $column) => DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(julianday(?) - julianday({$column}) AS INTEGER)"
            : "DATEDIFF(?, {$column})";

        $projects = Project::query()->visibleTo($user)->toBase()
            ->join('users', 'users.id', '=', 'projects.manager_id')
            ->whereNotIn('projects.status', $closed)
            ->whereDate('projects.planned_end_date', '<', $today)
            ->selectRaw("projects.id * 2 as id, 'project' as type, projects.id as project_id, projects.number, projects.title_ar, projects.title_en, NULL as stage_ar, NULL as stage_en, projects.manager_id, users.name as manager, projects.planned_end_date as planned_end, {$daysLate('projects.planned_end_date')} as days_late", [$today]);

        $stages = ProjectStage::query()->toBase()
            ->join('projects', 'projects.id', '=', 'project_stages.project_id')
            ->join('users', 'users.id', '=', 'projects.manager_id')
            ->whereIn('project_stages.project_id', Project::query()->visibleTo($user)->select('projects.id'))
            ->whereNotIn('projects.status', $closed)
            ->where('project_stages.status', '!=', StageStatus::Done->value)
            ->whereDate('project_stages.planned_end', '<', $today)
            ->selectRaw("project_stages.id * 2 + 1 as id, 'stage' as type, projects.id as project_id, projects.number, projects.title_ar, projects.title_en, project_stages.name_ar as stage_ar, project_stages.name_en as stage_en, projects.manager_id, users.name as manager, project_stages.planned_end as planned_end, {$daysLate('project_stages.planned_end')} as days_late", [$today]);

        return $projects->unionAll($stages);
    }

    protected function columns(): array
    {
        return [
            Column::make('type', 'reports.late_type')->format(fn (ReportRow $row) => __('reports.late_types.'.$row->getAttribute('type')))->onCard(),
            Column::make('number', 'projects.number')->sortable()->view('reports.partials.late-project')
                ->exportAs(fn (ReportRow $row) => (string) $row->getAttribute('number'))->onCard(),
            Column::make('title', 'projects.title')->format(fn (ReportRow $row) => self::localName($row, 'title')),
            Column::make('stage', 'reports.stage')->format(fn (ReportRow $row) => self::localName($row, 'stage'))->onCard(),
            Column::make('manager', 'projects.manager')->sortable(),
            Column::make('planned_end', 'reports.planned_end')->sortable()
                ->format(fn (ReportRow $row) => implode('/', array_reverse(explode('-', substr((string) $row->getAttribute('planned_end'), 0, 10))))),
            Column::make('days_late', 'reports.days_late')->sortable()->alignEnd()->format(fn (ReportRow $row) => (string) (int) $row->getAttribute('days_late'))->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('type', 'reports.late_type')
                ->options(fn () => ['project' => __('reports.late_types.project'), 'stage' => __('reports.late_types.stage')]),
            SelectFilter::make('manager_id', 'projects.manager')
                ->options(fn () => User::query()->whereIn('id', Project::query()->visibleTo(auth()->user())->select('manager_id'))->orderBy('name')->pluck('name', 'id')),
        ];
    }

    protected function searchColumns(): array
    {
        return ['number', 'title_ar', 'title_en', 'stage_ar', 'stage_en', 'manager'];
    }

    /** Lateness is always as of today. */
    protected function dateColumn(): ?string
    {
        return null;
    }

    protected function defaultSort(): array
    {
        return ['days_late', 'desc'];
    }
}
