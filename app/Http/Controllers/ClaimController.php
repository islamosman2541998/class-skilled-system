<?php

namespace App\Http\Controllers;

use App\Actions\Site\ApproveClaim;
use App\Actions\Site\ReturnClaim;
use App\Actions\Site\SaveClaim;
use App\Actions\Site\StartClaim;
use App\Actions\Site\SubmitClaim;
use App\Http\Requests\ReturnClaimRequest;
use App\Http\Requests\SaveClaimRequest;
use App\Models\Claim;
use App\Models\Project;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Progress claims (spec §5.9, §8.14). Drafts are edited; a submitted claim is frozen until it is
 * approved (Task 5) or returned to draft.
 */
class ClaimController extends Controller
{
    /**
     * Header buttons added by later tasks (approve, print), each receiving $claim.
     *
     * @var list<string>
     */
    public static array $actionViews = [];

    public function index(): View
    {
        Gate::authorize('claims.view');

        return view('claims.index');
    }

    public function store(Request $request, Project $project, StartClaim $start): RedirectResponse
    {
        abort_unless($project->isVisibleTo($request->user()), 404);
        Gate::authorize('claims.create');

        try {
            $claim = $start->handle($project, $request->user());
        } catch (DomainException $exception) {
            return redirect()->route('projects.show', [$project, 'tab' => 'claims'])->with('error', $exception->getMessage());
        }

        return redirect()->route('claims.edit', $claim)->with('success', __('site.claims.started'));
    }

    public function show(Request $request, Claim $claim): View
    {
        Gate::authorize('claims.view');
        $this->ensureVisible($request, $claim);

        return view('claims.show', ['claim' => $claim->load(['project', 'client', 'lines.unit', 'submitter', 'approver', 'creator', 'invoice'])]);
    }

    public function edit(Request $request, Claim $claim): View|RedirectResponse
    {
        Gate::authorize('claims.update');
        $this->ensureVisible($request, $claim);

        if (! $claim->isDraft()) {
            return $this->locked($claim);
        }

        return view('claims.form', ['claim' => $claim->load(['project', 'client', 'lines.unit'])]);
    }

    public function update(Request $request, Claim $claim, SaveClaim $save): RedirectResponse
    {
        Gate::authorize('claims.update');
        $this->ensureVisible($request, $claim);

        // Checked before validation, so a submitted claim never reaches the form rules.
        if (! $claim->isDraft()) {
            return $this->locked($claim);
        }

        try {
            $save->handle($claim, app(SaveClaimRequest::class)->validated(), $request->user());
        } catch (DomainException) {
            return $this->locked($claim);
        }

        return redirect()->route('claims.show', $claim)->with('success', __('app.saved'));
    }

    public function destroy(Request $request, Claim $claim): RedirectResponse
    {
        Gate::authorize('claims.delete');
        $this->ensureVisible($request, $claim);

        if (($reason = $claim->deletionBlockedReason($request->user())) !== null) {
            return back()->with('error', $reason);
        }

        $claim->delete();

        return redirect()->route('projects.show', [$claim->project_id, 'tab' => 'claims'])->with('success', __('site.claims.deleted'));
    }

    public function submit(Request $request, Claim $claim, SubmitClaim $submit): RedirectResponse
    {
        Gate::authorize('claims.update');
        $this->ensureVisible($request, $claim);

        $submit->handle($claim, $request->user());

        return redirect()->route('claims.show', $claim)->with('success', __('site.claims.submitted'));
    }

    public function return(ReturnClaimRequest $request, Claim $claim, ReturnClaim $return): RedirectResponse
    {
        $this->ensureVisible($request, $claim);

        try {
            $return->handle($claim, (string) $request->validated('return_note'), $request->user());
        } catch (DomainException $exception) {
            return redirect()->route('claims.show', $claim)->with('error', $exception->getMessage());
        }

        return redirect()->route('claims.show', $claim)->with('success', __('site.claims.returned'));
    }

    public function approve(Request $request, Claim $claim, ApproveClaim $approve): RedirectResponse
    {
        Gate::authorize('claims.approve');
        $this->ensureVisible($request, $claim);

        try {
            $approve->handle($claim, $request->user());
        } catch (DomainException $exception) {
            return redirect()->route('claims.show', $claim)->with('error', $exception->getMessage());
        }

        return redirect()->route('claims.show', $claim)->with('success', __('site.claims.approved'));
    }

    private function locked(Claim $claim): RedirectResponse
    {
        return redirect()->route('claims.show', $claim)->with('error', __('site.claims.locked'));
    }

    private function ensureVisible(Request $request, Claim $claim): void
    {
        abort_unless($claim->isVisibleTo($request->user()), 404);
    }
}
