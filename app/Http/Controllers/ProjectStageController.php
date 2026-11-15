<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Enums\StageStatus;
use App\Http\Requests\SaveProjectStageRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Services\ProjectProgress;
use App\Services\StageProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Stages of a project (spec §8.7). Every change refreshes the project's progress.
 */
class ProjectStageController extends Controller
{
    public function store(SaveProjectStageRequest $request, Project $project): RedirectResponse
    {
        $this->ensureVisible($request, $project);

        $stage = new ProjectStage(['sort_order' => (int) $project->stages()->max('sort_order') + 1, 'status' => StageStatus::NotStarted]);
        $this->apply($stage, $request->validated());
        $project->stages()->save($stage);
        ProjectProgress::refresh($project);

        return $this->back($project);
    }

    public function update(SaveProjectStageRequest $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        $this->ensureOwned($request, $project, $stage);

        $this->apply($stage, $request->validated());
        $stage->save();
        ProjectProgress::refresh($project);

        return $this->back($project);
    }

    public function destroy(Request $request, Project $project, ProjectStage $stage): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureOwned($request, $project, $stage);

        $stage->delete();
        ProjectProgress::refresh($project);

        return $this->back($project);
    }

    public function reorder(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('projects.update');
        $this->ensureVisible($request, $project);

        $ids = array_map('intval', (array) $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids']);

        DB::transaction(function () use ($project, $ids) {
            foreach ($ids as $position => $id) {
                $project->stages()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(['message' => __('app.saved')]);
    }

    public function attach(StoreAttachmentsRequest $request, Project $project, ProjectStage $stage, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureOwned($request, $project, $stage);

        $attach->handle($stage, $request->validated('uploads'), $request->user(), $request->meta());

        return $this->back($project);
    }

    /**
     * Status and progress stay consistent: done means 100, 100 means done, any progress means started.
     *
     * @param  array<string, mixed>  $data
     */
    private function apply(ProjectStage $stage, array $data): void
    {
        $stage->fill(collect($data)->except(['status', 'progress'])->all());

        StageProgress::apply(
            $stage,
            isset($data['progress']) ? (string) $data['progress'] : null,
            isset($data['status']) ? StageStatus::from($data['status']) : null,
            today(),
        );
    }

    private function back(Project $project): RedirectResponse
    {
        return redirect()->route('projects.show', [$project, 'tab' => 'stages'])->with('success', __('app.saved'));
    }

    private function ensureVisible(Request $request, Project $project): void
    {
        abort_unless($project->isVisibleTo($request->user()), 404);
    }

    private function ensureOwned(Request $request, Project $project, ProjectStage $stage): void
    {
        abort_unless($stage->project_id === $project->id && $project->isVisibleTo($request->user()), 404);
    }
}
