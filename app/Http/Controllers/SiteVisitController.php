<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Leads\EnsureClientForLead;
use App\Enums\DocumentType;
use App\Enums\NotificationType;
use App\Enums\SiteVisitStatus;
use App\Http\Requests\RecordVisitResultRequest;
use App\Http\Requests\SaveSiteVisitRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Client;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Notifier;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SiteVisitController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    public function index(): View
    {
        Gate::authorize('site_visits.view');

        return view('site-visits.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('site_visits.create');

        $lead = $request->integer('lead') ? Lead::query()->visibleTo($request->user())->find($request->integer('lead')) : null;

        $visit = new SiteVisit([
            'lead_id' => $lead?->id,
            'client_id' => $lead->client_id ?? ($request->integer('client') ?: null),
            'scheduled_at' => now()->addDay()->setTime(10, 0),
            'address' => $lead?->area_text,
        ]);

        return $this->form($visit, $lead);
    }

    public function store(SaveSiteVisitRequest $request, NumberGenerator $numbers, EnsureClientForLead $ensureClient): RedirectResponse
    {
        $visit = DB::transaction(function () use ($request, $numbers, $ensureClient) {
            $data = $request->validated();
            $lead = isset($data['lead_id']) ? Lead::query()->visibleTo($request->user())->findOrFail($data['lead_id']) : null;

            if ($lead !== null) {
                $data['client_id'] = $ensureClient->handle($lead, $request->user())->id;
                $lead->setStatusCode('visit', $request->user());
            }

            $visit = new SiteVisit($data);
            $visit->number = $numbers->next(DocumentType::SiteVisit);
            $visit->status = SiteVisitStatus::Scheduled;
            $visit->created_by = $request->user()->id;
            $visit->save();

            return $visit;
        });

        $this->notifyEngineer($visit, $request->user());

        return redirect()->route('site-visits.show', $visit)->with('success', __('app.saved'));
    }

    public function show(Request $request, SiteVisit $siteVisit): View
    {
        Gate::authorize('site_visits.view');
        $this->ensureVisible($request, $siteVisit);

        return view('site-visits.show', ['visit' => $siteVisit->load(['client', 'lead', 'engineer'])]);
    }

    public function edit(Request $request, SiteVisit $siteVisit): View
    {
        Gate::authorize('site_visits.update');
        $this->ensureVisible($request, $siteVisit);

        return $this->form($siteVisit, $siteVisit->lead);
    }

    public function update(SaveSiteVisitRequest $request, SiteVisit $siteVisit): RedirectResponse
    {
        $this->ensureVisible($request, $siteVisit);

        $previous = $siteVisit->engineer_id;
        $siteVisit->fill($request->safe()->except(['lead_id']))->save();

        if ($siteVisit->engineer_id !== $previous) {
            $this->notifyEngineer($siteVisit, $request->user());
        }

        return redirect()->route('site-visits.show', $siteVisit)->with('success', __('app.saved'));
    }

    /** The engineer's one-step result form, made for the phone (spec §4.4, §8.3). */
    public function result(Request $request, SiteVisit $siteVisit): View
    {
        Gate::authorize('site_visits.update');
        $this->ensureVisible($request, $siteVisit);

        return view('site-visits.result', ['visit' => $siteVisit->load('client')]);
    }

    public function storeResult(RecordVisitResultRequest $request, SiteVisit $siteVisit, AttachUploads $attach): RedirectResponse
    {
        $this->ensureVisible($request, $siteVisit);

        DB::transaction(function () use ($request, $siteVisit, $attach) {
            $siteVisit->fill([
                'status' => $request->validated('status'),
                'notes' => $request->validated('notes'),
                'measurements' => $request->validated('measurements'),
                'result_at' => now(),
            ])->save();

            if ($request->validated('uploads')) {
                $attach->handle($siteVisit, $request->validated('uploads'), $request->user());
            }
        });

        return redirect()->route('my-visits')->with('success', __('visits.result_saved'));
    }

    public function attach(StoreAttachmentsRequest $request, SiteVisit $siteVisit, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('site_visits.update');
        $this->ensureVisible($request, $siteVisit);

        $attach->handle($siteVisit, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function notifyEngineer(SiteVisit $visit, User $by): void
    {
        $engineer = User::query()->find($visit->engineer_id);

        if ($engineer !== null && ! $engineer->is($by)) {
            $this->notifier->toUser($engineer, NotificationType::Assigned, $visit, ['number' => $visit->number], route('site-visits.show', $visit));
        }
    }

    private function form(SiteVisit $visit, ?Lead $lead): View
    {
        return view('site-visits.form', [
            'visit' => $visit,
            'lead' => $lead,
            'clients' => $visit->client_id ? Client::query()->whereKey($visit->client_id)->get() : collect(),
            'engineers' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function ensureVisible(Request $request, SiteVisit $visit): void
    {
        abort_unless($visit->isVisibleTo($request->user()), 404);
    }
}
