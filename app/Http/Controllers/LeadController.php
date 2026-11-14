<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Leads\AssignLead;
use App\Enums\DocumentType;
use App\Enums\LookupType;
use App\Http\Requests\SaveLeadRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LeadController extends Controller
{
    /**
     * Blade partials shown under the lead details (follow-ups, visits, quotations …), each receiving $lead.
     *
     * @var list<string>
     */
    public static array $showViews = [];

    /**
     * Buttons added to the lead list header (for example "fetch website requests").
     *
     * @var list<string>
     */
    public static array $indexActionViews = [];

    public function __construct(private readonly AssignLead $assign) {}

    public function index(): View
    {
        Gate::authorize('leads.view');

        return view('leads.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('leads.create');

        $lead = new Lead([
            'client_id' => $request->integer('client') ?: null,
            'source_id' => null,
            'assigned_to' => $request->user()->id,
        ]);

        return $this->form($lead);
    }

    public function store(SaveLeadRequest $request, NumberGenerator $numbers): RedirectResponse
    {
        $lead = DB::transaction(function () use ($request, $numbers) {
            $lead = new Lead($request->validated());
            $lead->number = $numbers->next(DocumentType::Lead);
            $lead->status_id = (int) ($request->validated('status_id') ?? Lead::statusIdFor('new'));
            $lead->created_by = $request->user()->id;
            $lead->save();

            return $lead;
        });

        $this->assign->notifyIfNeeded($lead, $lead->assigned_to ? User::find($lead->assigned_to) : null, $request->user());

        return redirect()->route('leads.show', $lead)->with('success', __('app.saved'));
    }

    public function show(Request $request, Lead $lead): View
    {
        Gate::authorize('leads.view');
        $this->ensureVisible($request, $lead);

        $lead->load(['client', 'status', 'service', 'source', 'emirate', 'area', 'assignee', 'creator', 'rejectionReason']);

        return view('leads.show', [
            'lead' => $lead,
            'extraViews' => self::$showViews,
        ]);
    }

    public function edit(Request $request, Lead $lead): View
    {
        Gate::authorize('leads.update');
        $this->ensureVisible($request, $lead);

        return $this->form($lead);
    }

    public function update(SaveLeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->ensureVisible($request, $lead);

        $previousAssignee = $lead->assigned_to;
        $lead->fill($request->validated())->save();

        if ($lead->assigned_to !== $previousAssignee) {
            $this->assign->notifyIfNeeded($lead, $lead->assigned_to ? User::find($lead->assigned_to) : null, $request->user());
        }

        return redirect()->route('leads.show', $lead)->with('success', __('app.saved'));
    }

    public function attach(StoreAttachmentsRequest $request, Lead $lead, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('leads.update');
        $this->ensureVisible($request, $lead);

        $attach->handle($lead, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function form(Lead $lead): View
    {
        return view('leads.form', [
            'lead' => $lead,
            'clients' => $lead->client_id ? Client::query()->whereKey($lead->client_id)->get() : collect(),
            'services' => Lookup::options(LookupType::Service),
            'sources' => Lookup::options(LookupType::LeadSource),
            'statuses' => Lookup::options(LookupType::LeadStatus),
            'emirates' => Lookup::options(LookupType::Emirate),
            'areas' => Lookup::query()->ofType(LookupType::Area)->active()->orderBy('sort_order')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function ensureVisible(Request $request, Lead $lead): void
    {
        abort_unless($lead->isVisibleTo($request->user()), 404);
    }
}
