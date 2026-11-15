<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveClaimSetupRequest;
use App\Http\Requests\SaveProjectItemRequest;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Services\QuotationCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Claim setup and contract items of a project billed by claims (spec §5.9, §8.14). Both become
 * read-only once a claim of the project is submitted; the form requests refuse changes then.
 */
class ProjectItemController extends Controller
{
    public function store(SaveProjectItemRequest $request, Project $project): RedirectResponse
    {
        $item = new ProjectItem(['sort_order' => (int) $project->items()->max('sort_order') + 1]);
        $this->apply($item, $request->validated());
        $project->items()->save($item);

        return $this->back($project);
    }

    public function update(SaveProjectItemRequest $request, Project $project, ProjectItem $item): RedirectResponse
    {
        abort_unless($item->project_id === $project->id, 404);

        $this->apply($item, $request->validated());
        $item->save();

        return $this->back($project);
    }

    public function destroy(Request $request, Project $project, ProjectItem $item): RedirectResponse
    {
        abort_unless($item->project_id === $project->id && $project->isVisibleTo($request->user()) && $project->isClaimsBilled(), 404);
        Gate::authorize('projects.update');
        abort_if($project->hasLockedClaims(), 403);

        $item->delete();

        return $this->back($project);
    }

    public function setup(SaveClaimSetupRequest $request, Project $project): RedirectResponse
    {
        $project->forceFill([
            'retention_percent' => number_format((float) $request->validated('retention_percent'), 2, '.', ''),
            'contract_discount_percent' => number_format((float) $request->validated('contract_discount_percent'), 4, '.', ''),
            'advance_amount' => number_format((float) $request->validated('advance_amount'), 2, '.', ''),
        ])->save();

        return $this->back($project);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function apply(ProjectItem $item, array $data): void
    {
        $quantity = (string) $data['quantity'];
        $price = (string) $data['unit_price'];

        $item->fill([
            'section_ar' => $data['section_ar'] ?? null,
            'section_en' => $data['section_en'] ?? null,
            'description_ar' => $data['description_ar'],
            'description_en' => $data['description_en'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => QuotationCalculator::round(bcmul($quantity, $price, 6), 2),
        ]);
    }

    private function back(Project $project): RedirectResponse
    {
        return redirect()->route('projects.show', [$project, 'tab' => 'claims'])->with('success', __('app.saved'));
    }
}
