<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Site\SaveDailyReport;
use App\Http\Requests\SaveDailyReportRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\ProjectStage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Daily site reports (spec §8.13): a phone-first form in one step.
 */
class DailyReportController extends Controller
{
    public function index(): View
    {
        Gate::authorize('daily_reports.view');

        return view('daily-reports.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('daily_reports.create');

        $projects = $this->openProjects($request);
        $project = $projects->firstWhere('id', $request->integer('project')) ?? ($projects->count() === 1 ? $projects->first() : null);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) === 1 && $request->query('date') <= today()->toDateString()
            ? (string) $request->query('date')
            : today()->toDateString();

        return view('daily-reports.form', [
            'report' => new DailyReport(['project_id' => $project?->id, 'report_date' => $date, 'labour_count' => 0]),
            'projects' => $projects,
            'stagesByProject' => $this->stagesByProject($projects),
            'progress' => [],
        ]);
    }

    public function store(SaveDailyReportRequest $request, SaveDailyReport $save): RedirectResponse
    {
        $report = $save->handle($request->validated(), $request->user());

        return redirect()->route('daily-reports.show', $report)->with('success', __('site.reports.saved'));
    }

    public function show(Request $request, DailyReport $dailyReport): View
    {
        Gate::authorize('daily_reports.view');
        $this->ensureVisible($request, $dailyReport);

        return view('daily-reports.show', ['report' => $dailyReport->load(['project', 'engineer', 'stages.stage'])]);
    }

    public function edit(Request $request, DailyReport $dailyReport): View
    {
        Gate::authorize('daily_reports.update');
        $this->ensureVisible($request, $dailyReport);

        $projects = $this->openProjects($request)->push($dailyReport->project)->unique('id')->values();

        return view('daily-reports.form', [
            'report' => $dailyReport,
            'projects' => $projects,
            'stagesByProject' => $this->stagesByProject($projects),
            'progress' => $dailyReport->stages()->pluck('progress', 'project_stage_id')->map(fn ($progress) => rtrim(rtrim((string) $progress, '0'), '.') ?: '0')->all(),
        ]);
    }

    public function update(SaveDailyReportRequest $request, DailyReport $dailyReport, SaveDailyReport $save): RedirectResponse
    {
        $this->ensureVisible($request, $dailyReport);

        $save->handle($request->validated(), $request->user(), $dailyReport);

        return redirect()->route('daily-reports.show', $dailyReport)->with('success', __('site.reports.saved'));
    }

    public function attach(StoreAttachmentsRequest $request, DailyReport $dailyReport, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('daily_reports.update');
        $this->ensureVisible($request, $dailyReport);

        $attach->handle($dailyReport, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    /**
     * Projects the user sees that are not closed, with their stages for the form.
     *
     * @return Collection<int, Project>
     */
    private function openProjects(Request $request): Collection
    {
        return Project::query()->visibleTo($request->user())->whereNotIn('status', Project::closedStatuses())->latest('id')->get(['id', 'number', 'title_ar', 'title_en'])->collect();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, EloquentCollection<int, ProjectStage>>
     */
    private function stagesByProject(Collection $projects): Collection
    {
        $stages = ProjectStage::query()->whereIn('project_id', $projects->pluck('id'))->orderBy('sort_order')->orderBy('id')->get()->groupBy('project_id');

        return $projects->mapWithKeys(fn (Project $project) => [$project->id => $stages->get($project->id, new EloquentCollection)]);
    }

    private function ensureVisible(Request $request, DailyReport $report): void
    {
        abort_unless($report->isVisibleTo($request->user()), 404);
    }
}
