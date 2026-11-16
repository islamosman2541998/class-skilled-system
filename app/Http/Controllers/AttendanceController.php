<?php

namespace App\Http\Controllers;

use App\Actions\Resources\SaveAttendance;
use App\Http\Requests\SaveAttendanceRequest;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Daily attendance (spec §8.17): the list, and the sheet of one project and day for a group of
 * workers on one screen (phone first, §4.4).
 */
class AttendanceController extends Controller
{
    /**
     * Panels added by later tasks to the project resources tab (equipment), each receiving $project.
     *
     * @var list<string>
     */
    public static array $projectViews = [];

    public function index(): View
    {
        Gate::authorize('attendance.view');

        return view('attendance.index');
    }

    public function sheet(Request $request): View
    {
        abort_unless($request->user()->can('attendance.create') || $request->user()->can('attendance.update'), 403);

        $projects = Project::query()->visibleTo($request->user())->whereNotIn('status', Project::closedStatuses())->latest('id')->get(['id', 'number', 'title_ar', 'title_en']);
        $project = $request->integer('project') ? Project::query()->findOrFail($request->integer('project')) : null;
        abort_if($project !== null && ! $project->isVisibleTo($request->user()), 404);

        $date = $request->filled('date') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) ? Carbon::parse((string) $request->query('date')) : today();
        $date = $date->gt(today()) ? today() : $date;

        $rows = $project ? Attendance::query()->with('project')->whereDate('work_date', $date->toDateString())->get()->keyBy('worker_id') : collect();

        return view('attendance.sheet', [
            'projects' => $projects,
            'project' => $project,
            'date' => $date,
            'workers' => Worker::query()->with('trade')->where('is_active', true)->orWhereIn('id', $rows->keys())->orderBy('name_ar')->get(),
            'rows' => $rows,
        ]);
    }

    public function store(SaveAttendanceRequest $request, SaveAttendance $save): RedirectResponse
    {
        $project = Project::query()->findOrFail($request->integer('project_id'));
        abort_unless($project->isVisibleTo($request->user()), 404);

        $save->handle($project, Carbon::parse((string) $request->validated('work_date')), (array) $request->validated('rows'), $request->user());

        return redirect()->route('attendance.sheet', ['project' => $project->id, 'date' => $request->validated('work_date')])->with('success', __('resources.attendance.saved'));
    }
}
