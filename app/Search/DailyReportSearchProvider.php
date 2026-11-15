<?php

namespace App\Search;

use App\Models\DailyReport;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class DailyReportSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'daily_reports';
    }

    public function labelKey(): string
    {
        return 'site.reports.reports';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('daily_reports.view')) {
            return collect();
        }

        return Like::any(DailyReport::query()->with('project')->visibleTo($user), ['work_done', 'obstacles'], $term)
            ->latest('report_date')
            ->limit($limit)
            ->get()
            ->map(fn (DailyReport $report) => new SearchResult(
                $report->project->number.' · '.fmt_date($report->report_date),
                str($report->work_done)->limit(80)->toString(),
                route('daily-reports.show', $report),
            ));
    }
}
