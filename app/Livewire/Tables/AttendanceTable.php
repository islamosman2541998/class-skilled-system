<?php

namespace App\Livewire\Tables;

use App\Enums\AttendanceStatus;
use App\Enums\LookupType;
use App\Livewire\DataTable;
use App\Models\Attendance;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Worker;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

/** Attendance days (spec §8.17): filters project, worker, trade, status and the date. */
class AttendanceTable extends DataTable
{
    #[Locked]
    public ?int $projectId = null;

    public function tableKey(): string
    {
        return $this->projectId !== null ? 'attendance.project' : 'attendance';
    }

    protected function permissionPrefix(): ?string
    {
        return 'attendance';
    }

    protected function query(): Builder
    {
        return Attendance::query()
            ->with(['project', 'worker.trade'])
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId));
    }

    protected function columns(): array
    {
        return [
            Column::make('work_date', 'resources.attendance.date')->sortable()->format(fn (Attendance $row) => fmt_date($row->work_date))->onCard(),
            Column::make('project', 'resources.attendance.project')->format(fn (Attendance $row) => $row->project->number.' · '.$row->project->title),
            Column::make('worker', 'resources.attendance.worker')->format(fn (Attendance $row) => $row->worker->name)->onCard(),
            Column::make('trade', 'resources.workers.trade')->format(fn (Attendance $row) => (string) $row->worker->trade?->name),
            Column::make('status', 'resources.attendance.status')->sortable()->format(fn (Attendance $row) => $row->status->label())->onCard(),
            Column::make('overtime_hours', 'resources.attendance.overtime')->alignEnd()->format(fn (Attendance $row) => rtrim(rtrim((string) $row->overtime_hours, '0'), '.') ?: '0'),
            Column::make('cost', 'resources.attendance.cost')->sortable()->summable()->alignEnd()->format(fn (Attendance $row) => money($row->cost))->exportAs(fn (Attendance $row) => $row->cost)->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            $this->projectId === null ? SelectFilter::make('project_id', 'resources.attendance.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])) : null,
            SelectFilter::make('worker_id', 'resources.attendance.worker')->options(fn () => Worker::query()->orderBy('name_ar')->get()->mapWithKeys(fn (Worker $worker) => [$worker->id => $worker->name])),
            SelectFilter::make('trade_id', 'resources.workers.trade')->options(fn () => Lookup::options(LookupType::WorkerTrade)->pluck('name', 'id'))
                ->query(fn (Builder $query, mixed $value) => $query->whereHas('worker', fn (Builder $workers) => $workers->where('trade_id', $value))),
            SelectFilter::make('status', 'resources.attendance.status')
                ->options(fn () => collect(AttendanceStatus::cases())->mapWithKeys(fn (AttendanceStatus $status) => [$status->value => $status->label()])),
        ]));
    }

    protected function dateColumn(): ?string
    {
        return 'work_date';
    }

    protected function defaultSort(): array
    {
        return ['work_date', 'desc'];
    }
}
