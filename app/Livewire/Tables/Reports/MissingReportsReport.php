<?php

namespace App\Livewire\Tables\Reports;

use App\Models\Project;
use App\Models\ReportRow;
use App\Models\User;
use App\Services\Site\ReportCalendar;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Daily reports not sent (spec §8.13, §9.1): each working day without a report for the running
 * projects the user sees. The period defaults to the last 7 days ending yesterday.
 */
class MissingReportsReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.missing-reports';
    }

    protected function aggregate(): QueryBuilder
    {
        [$from, $to] = $this->period();
        $to ??= today()->subDay();
        $from ??= $to->copy()->subDays(6);

        return ReportCalendar::missingQuery(auth()->user(), $from, $to);
    }

    protected function columns(): array
    {
        return [
            Column::make('day', 'site.reports.date')->sortable()
                ->format(fn (ReportRow $row) => implode('/', array_reverse(explode('-', substr((string) $row->getAttribute('day'), 0, 10)))))->onCard(),
            Column::make('number', 'projects.number')->sortable()->view('reports.partials.missing-report-project')
                ->exportAs(fn (ReportRow $row) => (string) $row->getAttribute('number'))->onCard(),
            Column::make('title', 'projects.title')->format(fn (ReportRow $row) => self::localName($row, 'title')),
            Column::make('manager', 'projects.manager')->sortable()->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('manager_id', 'projects.manager')
                ->options(fn () => User::query()->whereIn('id', Project::query()->visibleTo(auth()->user())->select('manager_id'))->orderBy('name')->pluck('name', 'id')),
        ];
    }

    protected function searchColumns(): array
    {
        return ['number', 'title_ar', 'title_en', 'manager'];
    }

    protected function dateColumn(): ?string
    {
        return 'day';
    }

    protected function defaultSort(): array
    {
        return ['day', 'desc'];
    }
}
