<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Projects\DecideVariation;
use App\Actions\Projects\SaveVariation;
use App\Enums\LookupType;
use App\Http\Requests\SaveVariationRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Variation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use LogicException;

/**
 * Variation orders of a project (spec §8.9). Approved and rejected variations are read-only.
 */
class VariationController extends Controller
{
    public function create(Request $request, Project $project): View
    {
        Gate::authorize('projects.update');
        $this->ensureVisible($request, $project);

        return $this->form($project, new Variation);
    }

    public function store(SaveVariationRequest $request, Project $project, SaveVariation $save): RedirectResponse
    {
        $this->ensureVisible($request, $project);

        $variation = $save->handle($project, $request->validated(), $request->user());

        return redirect()->route('projects.variations.show', [$project, $variation])->with('success', __('app.saved'));
    }

    public function show(Request $request, Project $project, Variation $variation): View
    {
        Gate::authorize('projects.view');
        $this->ensureOwned($request, $project, $variation);

        return view('variations.show', ['project' => $project, 'variation' => $variation->load(['items.unit', 'decider'])]);
    }

    public function edit(Request $request, Project $project, Variation $variation): View|RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureOwned($request, $project, $variation);

        if ($variation->isDecided()) {
            return $this->locked($project, $variation);
        }

        return $this->form($project, $variation->load('items'));
    }

    public function update(Request $request, Project $project, Variation $variation, SaveVariation $save): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureOwned($request, $project, $variation);

        // Checked before validation, so a decided variation never reaches the form rules.
        if ($variation->isDecided()) {
            return $this->locked($project, $variation);
        }

        $save->handle($project, app(SaveVariationRequest::class)->validated(), $request->user(), $variation);

        return redirect()->route('projects.variations.show', [$project, $variation])->with('success', __('app.saved'));
    }

    public function approve(Request $request, Project $project, Variation $variation, DecideVariation $decide): RedirectResponse
    {
        Gate::authorize('projects.approve');
        $this->ensureOwned($request, $project, $variation);

        try {
            $decide->approve($variation, $request->user());
        } catch (LogicException) {
            return $this->locked($project, $variation);
        }

        return redirect()->route('projects.variations.show', [$project, $variation])->with('success', __('projects.variations.approved'));
    }

    public function reject(Request $request, Project $project, Variation $variation, DecideVariation $decide): RedirectResponse
    {
        Gate::authorize('projects.approve');
        $this->ensureOwned($request, $project, $variation);

        $note = $request->validate(['rejection_note' => ['nullable', 'string', 'max:2000']])['rejection_note'] ?? null;

        try {
            $decide->reject($variation, $note, $request->user());
        } catch (LogicException) {
            return $this->locked($project, $variation);
        }

        return redirect()->route('projects.variations.show', [$project, $variation])->with('success', __('projects.variations.rejected'));
    }

    public function attach(StoreAttachmentsRequest $request, Project $project, Variation $variation, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureOwned($request, $project, $variation);

        $attach->handle($variation, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function form(Project $project, Variation $variation): View
    {
        return view('variations.form', [
            'project' => $project,
            'variation' => $variation,
            'items' => old('items', $variation->exists
                ? $variation->items->map->only(['description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price'])->all()
                : [['description_ar' => '', 'quantity' => '1', 'unit_price' => '0']]),
            'units' => Lookup::options(LookupType::Unit),
        ]);
    }

    private function locked(Project $project, Variation $variation): RedirectResponse
    {
        return redirect()->route('projects.variations.show', [$project, $variation])->with('error', __('projects.variations.locked'));
    }

    private function ensureVisible(Request $request, Project $project): void
    {
        abort_unless($project->isVisibleTo($request->user()), 404);
    }

    private function ensureOwned(Request $request, Project $project, Variation $variation): void
    {
        abort_unless($variation->project_id === $project->id && $project->isVisibleTo($request->user()), 404);
    }
}
