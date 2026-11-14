<?php

namespace App\Http\Controllers;

use App\Enums\InstallmentDueType;
use App\Enums\InstallmentStatus;
use App\Http\Requests\SaveInstallmentRequest;
use App\Models\Project;
use App\Models\ProjectInstallment;
use App\Services\InstallmentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Payment schedule of a project (spec §8.8). Invoiced and collected lines are read-only.
 */
class ProjectInstallmentController extends Controller
{
    public function store(SaveInstallmentRequest $request, Project $project): RedirectResponse
    {
        $this->ensureVisible($request, $project);

        $installment = new ProjectInstallment([
            'status' => InstallmentStatus::NotDue,
            'sort_order' => (int) $project->installments()->max('sort_order') + 1,
        ]);
        $this->apply($installment, $project, $request->validated());
        $project->installments()->save($installment);
        InstallmentSchedule::markDue($project);

        return $this->back($project);
    }

    public function update(Request $request, Project $project, ProjectInstallment $installment): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureOwned($request, $project, $installment);

        // Checked before validation, so an invoiced line never reaches the form rules.
        if ($installment->status->isLocked()) {
            return $this->back($project, __('projects.installments.locked'));
        }

        $this->apply($installment, $project, app(SaveInstallmentRequest::class)->validated());
        $installment->save();
        InstallmentSchedule::markDue($project);

        return $this->back($project);
    }

    public function destroy(Request $request, Project $project, ProjectInstallment $installment): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureOwned($request, $project, $installment);

        if ($installment->status->isLocked()) {
            return $this->back($project, __('projects.installments.locked'));
        }

        $installment->delete();

        return $this->back($project);
    }

    /**
     * Either the percent or the amount is entered; the other is derived from the contract value.
     *
     * @param  array<string, mixed>  $data
     */
    private function apply(ProjectInstallment $installment, Project $project, array $data): void
    {
        $installment->fill([
            'label_ar' => $data['label_ar'],
            'label_en' => $data['label_en'] ?? null,
            'due_type' => $data['due_type'],
            'due_date' => $data['due_type'] === InstallmentDueType::Date->value ? ($data['due_date'] ?? null) : null,
            'stage_id' => $data['due_type'] === InstallmentDueType::Stage->value ? ($data['stage_id'] ?? null) : null,
        ]);

        if (filled($data['percent'] ?? null)) {
            $installment->percent = (string) $data['percent'];
            $installment->amount = InstallmentSchedule::amountFor($project, (string) $data['percent']);
        } else {
            $installment->amount = number_format((float) $data['amount'], 2, '.', '');
            $installment->percent = InstallmentSchedule::percentFor($project, $installment->amount);
        }

        // A changed date in the future puts a due line back to not due.
        if ($installment->status === InstallmentStatus::Due) {
            $installment->status = InstallmentStatus::NotDue;
        }
    }

    private function back(Project $project, ?string $error = null): RedirectResponse
    {
        $redirect = redirect()->route('projects.show', [$project, 'tab' => 'payments']);

        return $error === null ? $redirect->with('success', __('app.saved')) : $redirect->with('error', $error);
    }

    private function ensureVisible(Request $request, Project $project): void
    {
        abort_unless($project->isVisibleTo($request->user()), 404);
    }

    private function ensureOwned(Request $request, Project $project, ProjectInstallment $installment): void
    {
        abort_unless($installment->project_id === $project->id && $project->isVisibleTo($request->user()), 404);
    }
}
