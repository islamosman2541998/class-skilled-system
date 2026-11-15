<?php

namespace App\Actions\Site;

use App\Actions\AttachUploads;
use App\Models\DailyReport;
use App\Models\DailyReportStage;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use App\Services\ProjectProgress;
use App\Services\StageProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a daily report (spec §8.13). One live report per project per day: the project
 * row is locked so two engineers saving at once leave one report. Each stage then takes the
 * progress of its latest-dated report, so an older report never lowers a newer one.
 */
class SaveDailyReport
{
    public function __construct(private readonly AttachUploads $attach) {}

    /**
     * @param  array<string, mixed>  $data  validated SaveDailyReportRequest data
     */
    public function handle(array $data, User $by, ?DailyReport $report = null): DailyReport
    {
        return DB::transaction(function () use ($data, $by, $report) {
            $project = Project::query()->lockForUpdate()->findOrFail($data['project_id']);
            $date = Carbon::parse($data['report_date'])->startOfDay();

            $existing = DailyReport::query()
                ->where('project_id', $project->id)
                ->whereDate('report_date', $date->toDateString())
                ->when($report, fn ($query) => $query->whereKeyNot($report->id))
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'report_date' => __('site.reports.exists', ['url' => route('daily-reports.show', $existing)]),
                ]);
            }

            $report ??= new DailyReport;
            $creating = ! $report->exists;
            $touched = $report->exists ? $report->stages()->pluck('project_stage_id')->all() : [];

            $report->fill([
                'project_id' => $project->id,
                'report_date' => $date,
                'work_done' => $data['work_done'],
                'labour_count' => (int) ($data['labour_count'] ?? 0),
                'obstacles' => $data['obstacles'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $report->has_obstacles = filled($data['obstacles'] ?? null);

            if ($creating) {
                $report->engineer_id = $by->id;
                $report->created_by = $by->id;
            }

            $report->save();

            $stages = collect($data['stages'] ?? [])->filter(fn ($progress) => $progress !== null && $progress !== '');
            $report->stages()->whereNotIn('project_stage_id', $stages->keys()->all())->delete();

            foreach ($stages as $stageId => $progress) {
                $report->stages()->updateOrCreate(['project_stage_id' => (int) $stageId], ['progress' => (string) $progress]);
            }

            $this->applyLatest($project, array_unique([...$touched, ...$stages->keys()->map(fn ($id) => (int) $id)->all()]));

            if (! empty($data['uploads'])) {
                $this->attach->handle($report, $data['uploads'], $by);
            }

            return $report;
        });
    }

    /**
     * Sets each stage to the progress of its latest-dated live report and refreshes the project.
     *
     * @param  list<int>  $stageIds
     */
    private function applyLatest(Project $project, array $stageIds): void
    {
        foreach (ProjectStage::query()->where('project_id', $project->id)->whereIn('id', $stageIds)->get() as $stage) {
            $latest = DailyReportStage::query()
                ->join('daily_reports', 'daily_reports.id', '=', 'daily_report_stages.daily_report_id')
                ->whereNull('daily_reports.deleted_at')
                ->where('daily_report_stages.project_stage_id', $stage->id)
                ->orderByDesc('daily_reports.report_date')
                ->orderByDesc('daily_reports.id')
                ->first(['daily_report_stages.progress', 'daily_reports.report_date']);

            if ($latest === null) {
                continue;
            }

            StageProgress::apply($stage, (string) $latest->progress, null, Carbon::parse((string) $latest->getAttribute('report_date')));
            $stage->save();
        }

        ProjectProgress::refresh($project);
    }
}
