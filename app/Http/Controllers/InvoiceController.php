<?php

namespace App\Http\Controllers;

use App\Actions\Finance\CancelInvoice;
use App\Actions\Finance\IssueInvoice;
use App\Actions\Finance\SaveInvoice;
use App\Enums\LookupType;
use App\Http\Requests\CancelDocumentRequest;
use App\Http\Requests\SaveInvoiceRequest;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Project;
use App\Settings\FinanceSettings;
use App\Settings\TemplateSettings;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Tax invoices (spec §8.10).
 */
class InvoiceController extends Controller
{
    /**
     * Blade partials (receiving $invoice) with extra page actions added by later tasks (PDF, receipts).
     *
     * @var list<string>
     */
    public static array $actionViews = [];

    /**
     * Blade partials (receiving $invoice) shown under the items on the invoice page.
     *
     * @var list<string>
     */
    public static array $showViews = [];

    public function index(): View
    {
        Gate::authorize('invoices.view');

        return view('invoices.index');
    }

    public function create(Request $request, FinanceSettings $finance, TemplateSettings $templates): View
    {
        Gate::authorize('invoices.create');

        $project = $request->integer('project') ? Project::query()->visibleTo($request->user())->find($request->integer('project')) : null;

        $invoice = new Invoice([
            'project_id' => $project?->id,
            'issue_date' => today(),
            'due_date' => today()->addDays($finance->invoice_due_days),
            'terms_ar' => $templates->invoice_terms_ar,
            'terms_en' => $templates->invoice_terms_en,
        ]);

        return $this->form($request, $invoice);
    }

    public function store(SaveInvoiceRequest $request, SaveInvoice $save): RedirectResponse
    {
        $invoice = $save->handle($request->validated(), $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', __('app.saved'));
    }

    public function show(Request $request, Invoice $invoice): View
    {
        Gate::authorize('invoices.view');
        $this->ensureVisible($request, $invoice);

        return view('invoices.show', ['invoice' => $invoice->load(['project', 'client', 'items.unit', 'installment', 'replaces', 'issuer', 'canceller'])]);
    }

    public function edit(Request $request, Invoice $invoice): View|RedirectResponse
    {
        Gate::authorize('invoices.update');
        $this->ensureVisible($request, $invoice);

        if (! $invoice->isDraft()) {
            return $this->locked($invoice);
        }

        return $this->form($request, $invoice->load('items'));
    }

    public function update(Request $request, Invoice $invoice, SaveInvoice $save): RedirectResponse
    {
        Gate::authorize('invoices.update');
        $this->ensureVisible($request, $invoice);

        // Checked before validation, so an issued invoice never reaches the form rules.
        if (! $invoice->isDraft()) {
            return $this->locked($invoice);
        }

        $save->handle(app(SaveInvoiceRequest::class)->validated(), $request->user(), $invoice);

        return redirect()->route('invoices.show', $invoice)->with('success', __('app.saved'));
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('invoices.delete');
        $this->ensureVisible($request, $invoice);

        if (($reason = $invoice->deletionBlockedReason($request->user())) !== null) {
            return back()->with('error', $reason);
        }

        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', __('finance.invoices.deleted'));
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        Gate::authorize('invoices.update');
        $this->ensureVisible($request, $invoice);

        $issue->handle($invoice, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', __('finance.invoices.issued'));
    }

    public function cancel(CancelDocumentRequest $request, Invoice $invoice, CancelInvoice $cancel): RedirectResponse
    {
        Gate::authorize('invoices.cancel');
        $this->ensureVisible($request, $invoice);

        try {
            $copy = $cancel->handle($invoice, (string) $request->validated('reason'), $request->user(), $request->boolean('replace'));
        } catch (DomainException $exception) {
            return redirect()->route('invoices.show', $invoice)->with('error', $exception->getMessage());
        }

        return $copy !== null
            ? redirect()->route('invoices.edit', $copy)->with('success', __('finance.invoices.replaced'))
            : redirect()->route('invoices.show', $invoice)->with('success', __('finance.invoices.cancelled'));
    }

    private function form(Request $request, Invoice $invoice): View
    {
        return view('invoices.form', [
            'invoice' => $invoice,
            'projects' => Project::query()->visibleTo($request->user())->with('client')->latest('id')->get(['id', 'number', 'title_ar', 'title_en', 'client_id', 'vat_rate']),
            'items' => old('items', $invoice->exists
                ? $invoice->items->map->only(['description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price'])->all()
                : [['description_ar' => '', 'quantity' => '1', 'unit_price' => '0']]),
            'units' => Lookup::options(LookupType::Unit),
        ]);
    }

    private function locked(Invoice $invoice): RedirectResponse
    {
        return redirect()->route('invoices.show', $invoice)->with('error', __('finance.invoices.locked'));
    }

    private function ensureVisible(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->isVisibleTo($request->user()), 404);
    }
}
