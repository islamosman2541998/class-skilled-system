<?php

namespace App\Http\Controllers;

use App\Actions\Leads\AssignLead;
use App\Actions\Leads\CloseLeadAsLost;
use App\Http\Requests\LeadActionRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Actions from the lead page (spec §8.2): follow-up log, assign, schedule, close as lost, reopen.
 */
class LeadActionController extends Controller
{
    public function followUp(LeadActionRequest $request, Lead $lead): RedirectResponse
    {
        $this->ensureVisible($request, $lead);

        $lead->followUps()->create([
            'type' => $request->validated('type'),
            'body' => $request->validated('body'),
            'user_id' => $request->user()->id,
            'happened_at' => $request->validated('happened_at') ?? now(),
        ]);

        if ($lead->statusCode() === 'new') {
            $lead->setStatusCode('contacted', $request->user());
        }

        return back()->with('success', __('app.saved'));
    }

    public function assign(LeadActionRequest $request, Lead $lead, AssignLead $assign): RedirectResponse
    {
        $this->ensureVisible($request, $lead);

        $id = $request->validated('assigned_to');
        $assign->handle($lead, $id ? User::query()->find($id) : null, $request->user());

        return back()->with('success', __('app.saved'));
    }

    public function schedule(LeadActionRequest $request, Lead $lead): RedirectResponse
    {
        $this->ensureVisible($request, $lead);

        $lead->update(['follow_up_at' => $request->validated('follow_up_at')]);

        return back()->with('success', __('app.saved'));
    }

    public function close(LeadActionRequest $request, Lead $lead, CloseLeadAsLost $close): RedirectResponse
    {
        $this->ensureVisible($request, $lead);

        $close->handle($lead, (int) $request->validated('rejection_reason_id'), $request->validated('rejection_note'), $request->user());

        return back()->with('success', __('leads.closed'));
    }

    public function reopen(LeadActionRequest $request, Lead $lead): RedirectResponse
    {
        $this->ensureVisible($request, $lead);

        $lead->reopen($request->user());

        return back()->with('success', __('app.saved'));
    }

    private function ensureVisible(LeadActionRequest $request, Lead $lead): void
    {
        abort_unless($lead->isVisibleTo($request->user()), 404);
    }
}
