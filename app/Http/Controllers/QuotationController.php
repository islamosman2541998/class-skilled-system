<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Quotations\SaveQuotation;
use App\Enums\LookupType;
use App\Http\Requests\SaveQuotationRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Quotation;
use App\Models\User;
use App\Settings\FinanceSettings;
use App\Settings\TemplateSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('quotations.view');

        return view('quotations.index');
    }

    public function create(Request $request, FinanceSettings $finance, TemplateSettings $templates): View
    {
        Gate::authorize('quotations.create');

        $lead = $request->integer('lead') ? Lead::query()->visibleTo($request->user())->find($request->integer('lead')) : null;

        $quotation = new Quotation([
            'lead_id' => $lead?->id,
            'client_id' => $lead->client_id ?? ($request->integer('client') ?: null),
            'service_id' => $lead?->service_id,
            'emirate_id' => $lead?->emirate_id,
            'location' => $lead?->area_text,
            'title_ar' => $lead?->service->name_ar ?? '',
            'issue_date' => today(),
            'valid_until' => today()->addDays($finance->quotation_validity_days),
            'assigned_to' => $lead->assigned_to ?? $request->user()->id,
            'terms_ar' => $templates->quotation_terms_ar,
            'terms_en' => $templates->quotation_terms_en,
        ]);

        $template = $templates->payment_templates[0] ?? ['lines' => []];

        return $this->form($quotation, $lead, [], $template['lines']);
    }

    public function store(SaveQuotationRequest $request, SaveQuotation $save): RedirectResponse
    {
        if ($request->validated('lead_id')) {
            abort_unless(Lead::query()->findOrFail($request->validated('lead_id'))->isVisibleTo($request->user()), 404);
        }

        $quotation = $save->handle($request->validated(), $request->user());

        return redirect()->route('quotations.show', $quotation)->with('success', __('app.saved'));
    }

    public function show(Request $request, Quotation $quotation): View
    {
        Gate::authorize('quotations.view');
        $this->ensureVisible($request, $quotation);

        $quotation->load(['client', 'lead', 'service', 'emirate', 'assignee', 'creator', 'items.unit', 'payments', 'rejectionReason']);

        $rootId = $quotation->parent_id ?? $quotation->id;
        $revisions = Quotation::query()->where(fn ($q) => $q->whereKey($rootId)->orWhere('parent_id', $rootId))->orderBy('revision')->get();

        return view('quotations.show', [
            'quotation' => $quotation,
            'revisions' => $revisions,
            'actionViews' => self::$actionViews,
        ]);
    }

    public function edit(Request $request, Quotation $quotation, TemplateSettings $templates): View|RedirectResponse
    {
        Gate::authorize('quotations.update');
        $this->ensureVisible($request, $quotation);

        if ($quotation->isLocked()) {
            return redirect()->route('quotations.show', $quotation)->with('error', __('quotations.locked'));
        }

        $quotation->load(['items', 'payments']);

        return $this->form(
            $quotation,
            $quotation->lead,
            $quotation->items->map->only(['section_ar', 'section_en', 'price_item_id', 'description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price'])->all(),
            $quotation->payments->map->only(['label_ar', 'label_en', 'percent'])->all(),
        );
    }

    public function update(Request $request, Quotation $quotation, SaveQuotation $save): RedirectResponse
    {
        Gate::authorize('quotations.update');
        $this->ensureVisible($request, $quotation);

        // The lock is checked before validation, so a locked quotation never reaches the form rules.
        if ($quotation->isLocked()) {
            return redirect()->route('quotations.show', $quotation)->with('error', __('quotations.locked'));
        }

        $save->handle(app(SaveQuotationRequest::class)->validated(), $request->user(), $quotation);

        return redirect()->route('quotations.show', $quotation)->with('success', __('app.saved'));
    }

    public function attach(StoreAttachmentsRequest $request, Quotation $quotation, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('quotations.update');
        $this->ensureVisible($request, $quotation);

        $attach->handle($quotation, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    /**
     * Header buttons of the quotation page added by later tasks and stages, each receiving $quotation.
     *
     * @var list<string>
     */
    public static array $actionViews = [];

    /**
     * Prominent buttons placed before the workflow buttons (PDF, WhatsApp, convert to project).
     *
     * @var list<string>
     */
    public static array $primaryActionViews = [];

    /**
     * Items added to the "actions" menu of a quotation, each a <li> receiving $quotation.
     *
     * @var list<string>
     */
    public static array $menuActionViews = [];

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $payments
     */
    private function form(Quotation $quotation, ?Lead $lead, array $items, array $payments): View
    {
        return view('quotations.form', [
            'quotation' => $quotation,
            'lead' => $lead,
            'items' => old('items', $items ?: [['section_ar' => '', 'description_ar' => '', 'quantity' => '1', 'unit_price' => '0']]),
            'payments' => old('payments', $payments),
            'clients' => $quotation->client_id ? Client::query()->whereKey($quotation->client_id)->get() : collect(),
            'services' => Lookup::options(LookupType::Service),
            'emirates' => Lookup::options(LookupType::Emirate),
            'units' => Lookup::options(LookupType::Unit),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'paymentTemplates' => app(TemplateSettings::class)->payment_templates,
            'finance' => app(FinanceSettings::class),
        ]);
    }

    private function ensureVisible(Request $request, Quotation $quotation): void
    {
        abort_unless($quotation->isVisibleTo($request->user()), 404);
    }
}
