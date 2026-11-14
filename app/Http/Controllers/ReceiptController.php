<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Finance\AllocateReceipt;
use App\Actions\Finance\CancelReceipt;
use App\Actions\Finance\SaveReceipt;
use App\Enums\LookupType;
use App\Http\Requests\CancelDocumentRequest;
use App\Http\Requests\SaveReceiptRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Receipt vouchers and their allocation to invoices (spec §8.11).
 */
class ReceiptController extends Controller
{
    public function index(): View
    {
        Gate::authorize('receipts.view');

        return view('receipts.index');
    }

    /** Opened from an invoice (?invoice=), a client (?client=) or empty; the client decides the open invoices. */
    public function create(Request $request): View
    {
        Gate::authorize('receipts.create');

        $user = $request->user();
        $invoice = $request->integer('invoice') ? Invoice::query()->visibleTo($user)->active()->find($request->integer('invoice')) : null;
        $client = $invoice->client ?? ($request->integer('client') ? Client::query()->visibleTo($user)->find($request->integer('client')) : null);

        $receipt = new Receipt([
            'receipt_date' => today(),
            'received_by' => $user->id,
            'amount' => $invoice?->remaining(),
            'payment_method_id' => Lookup::options(LookupType::PaymentMethod)->first()?->id,
        ]);
        $receipt->client_id = $client->id ?? 0;
        $receipt->project_id = $invoice?->project_id;

        return view('receipts.form', [
            'receipt' => $receipt,
            'client' => $client,
            'projects' => $client ? Project::query()->visibleTo($user)->where('client_id', $client->id)->latest('id')->get() : collect(),
            'openInvoices' => $client ? $this->openInvoices($user, $client->id) : new Collection,
            'allocations' => old('allocations', $invoice ? [$invoice->id => $invoice->remaining()] : []),
            'methods' => Lookup::options(LookupType::PaymentMethod),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(SaveReceiptRequest $request, SaveReceipt $save, AllocateReceipt $allocate): RedirectResponse
    {
        // One transaction: a refused allocation also gives the receipt number back.
        $receipt = DB::transaction(function () use ($request, $save, $allocate) {
            $receipt = $save->handle($request->validated(), $request->user());
            $allocate->handle($receipt, (array) $request->validated('allocations', []), $request->user());

            return $receipt;
        });

        return redirect()->route('receipts.show', $receipt)->with('success', __('app.saved'));
    }

    public function show(Request $request, Receipt $receipt): View
    {
        Gate::authorize('receipts.view');
        $this->ensureVisible($request, $receipt);

        $receipt->load(['client', 'project', 'paymentMethod', 'receiver', 'canceller', 'allocations.invoice']);

        return view('receipts.show', [
            'receipt' => $receipt,
            'openInvoices' => $receipt->isActive() ? $this->openInvoices($request->user(), $receipt->client_id, $receipt) : new Collection,
        ]);
    }

    public function edit(Request $request, Receipt $receipt): View|RedirectResponse
    {
        Gate::authorize('receipts.update');
        $this->ensureVisible($request, $receipt);

        if (! $receipt->isActive()) {
            return redirect()->route('receipts.show', $receipt)->with('error', __('finance.receipts.cancelled_locked'));
        }

        return view('receipts.form', [
            'receipt' => $receipt->load(['client', 'project']),
            'client' => $receipt->client,
            'projects' => collect(),
            'openInvoices' => new Collection,
            'allocations' => [],
            'methods' => Lookup::options(LookupType::PaymentMethod),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Receipt $receipt, SaveReceipt $save): RedirectResponse
    {
        Gate::authorize('receipts.update');
        $this->ensureVisible($request, $receipt);

        // Checked before validation, so a cancelled receipt never reaches the form rules.
        if (! $receipt->isActive()) {
            return redirect()->route('receipts.show', $receipt)->with('error', __('finance.receipts.cancelled_locked'));
        }

        $save->handle(app(SaveReceiptRequest::class)->validated(), $request->user(), $receipt);

        return redirect()->route('receipts.show', $receipt)->with('success', __('app.saved'));
    }

    /** Settles the advance against invoices, or changes how the receipt is spread over them. */
    public function allocate(Request $request, Receipt $receipt, AllocateReceipt $allocate): RedirectResponse
    {
        Gate::authorize('receipts.update');
        $this->ensureVisible($request, $receipt);

        $data = $request->validate([
            'allocations' => ['nullable', 'array', 'max:200'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        $allocate->handle($receipt, (array) ($data['allocations'] ?? []), $request->user());

        return redirect()->route('receipts.show', $receipt)->with('success', __('finance.receipts.allocated_ok'));
    }

    public function cancel(CancelDocumentRequest $request, Receipt $receipt, CancelReceipt $cancel): RedirectResponse
    {
        $this->ensureVisible($request, $receipt);

        $cancel->handle($receipt, (string) $request->validated('reason'), $request->user());

        return redirect()->route('receipts.show', $receipt)->with('success', __('finance.receipts.cancelled'));
    }

    public function attach(StoreAttachmentsRequest $request, Receipt $receipt, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('receipts.update');
        $this->ensureVisible($request, $receipt);

        $attach->handle($receipt, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    /**
     * Active invoices of the client with something left to pay, plus those this receipt already pays.
     *
     * @return Collection<int, Invoice>
     */
    private function openInvoices(User $user, int $clientId, ?Receipt $receipt = null): Collection
    {
        $paidByReceipt = $receipt?->allocations->pluck('invoice_id')->all() ?? [];

        return Invoice::query()->visibleTo($user)->active()->with('project')
            ->where('client_id', $clientId)
            ->when($receipt?->project_id, fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->where(fn ($query) => $query->whereColumn('paid_amount', '<', 'total')->orWhereIn('id', $paidByReceipt))
            ->orderBy('due_date')
            ->get();
    }

    private function ensureVisible(Request $request, Receipt $receipt): void
    {
        abort_unless($receipt->isVisibleTo($request->user()), 404);
    }
}
