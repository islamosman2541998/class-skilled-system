<?php

namespace App\Services\Site;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Settings\SystemSettings;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Days without a daily report (spec §8.13): for running projects, each working day from the project
 * start up to yesterday with no report. Weekly days off come from the system settings; today never
 * counts, a report may still come.
 */
final class ReportCalendar
{
    /** The longest period the missing-reports list covers. */
    public const MAX_DAYS = 62;

    /**
     * Working days (Y-m-d) between the dates, both included, ending yesterday at the latest.
     *
     * @return list<string>
     */
    public static function workingDays(Carbon $from, Carbon $to): array
    {
        $off = array_map('intval', app(SystemSettings::class)->weekly_days_off ?? []);
        $end = $to->copy()->startOfDay()->min(today()->subDay());
        $days = [];

        for ($day = $from->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            if (! in_array($day->isoWeekday(), $off, true)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    /**
     * @return list<string>
     */
    public static function missingDays(Project $project, Carbon $from, Carbon $to): array
    {
        if ($project->status !== ProjectStatus::InProgress || $project->start_date === null) {
            return [];
        }

        $start = $from->copy()->max($project->start_date);
        $reported = $project->dailyReports()->pluck('report_date')->map(fn (Carbon $date) => $date->toDateString())->all();

        return array_values(array_diff(self::workingDays($start, $to), $reported));
    }

    /**
     * Rows project × missing day for the running projects the user sees: day, idx, project_id,
     * number, title_ar, title_en, manager_id, manager and a unique id. Built from one constant
     * SELECT per day, so it runs the same on MySQL and SQLite.
     */
    public static function missingQuery(User $user, Carbon $from, Carbon $to): QueryBuilder
    {
        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->copy()->subDays(self::MAX_DAYS - 1);
        }

        $days = null;

        foreach (self::workingDays($from, $to) as $index => $day) {
            $select = DB::query()->selectRaw('? as day, ? as idx', [$day, $index]);
            $days = $days === null ? $select : $days->unionAll($select);
        }

        // No working day in the period: an empty set with the same columns.
        $days ??= DB::query()->selectRaw('? as day, ? as idx', ['1970-01-01', 0])->whereRaw('1 = 0');

        $projects = Project::query()->visibleTo($user)
            ->where('projects.status', ProjectStatus::InProgress->value)
            ->whereNotNull('projects.start_date')
            ->toBase()
            ->select(['projects.id', 'projects.number', 'projects.title_ar', 'projects.title_en', 'projects.manager_id', 'projects.start_date']);

        return DB::query()->fromSub($days, 'd')
            ->crossJoinSub($projects, 'p')
            ->leftJoin('users as m', 'm.id', '=', 'p.manager_id')
            ->whereRaw('DATE(p.start_date) <= d.day')
            ->whereNotExists(fn (QueryBuilder $reports) => $reports->selectRaw('1')->from('daily_reports as r')
                ->whereColumn('r.project_id', 'p.id')
                ->whereRaw('DATE(r.report_date) = d.day')
                ->whereNull('r.deleted_at'))
            ->selectRaw('p.id * 100 + d.idx as id, d.day, d.idx, p.id as project_id, p.number, p.title_ar, p.title_en, p.manager_id, m.name as manager');
    }
}
