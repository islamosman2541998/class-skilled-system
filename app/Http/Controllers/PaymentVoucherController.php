<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Resources\AllocatePaymentVoucher;
use App\Actions\Resources\CancelPaymentVoucher;
use App\Actions\Resources\SavePaymentVoucher;
use App\Enums\LookupType;
use App\Http\Requests\CancelDocumentRequest;
use App\Http\Requests\SavePaymentVoucherRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Lookup;
use App\Models\PaymentVoucher;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Payment vouchers to suppliers and subcontractors (spec §8.16), spread over their open purchase
 * invoices; cancelled with a reason, never deleted.
 */
class PaymentVoucherController extends Controller
{
    public function index(): View
    {
        Gate::authorize('payment_vouchers.view');

        return view('payment-vouchers.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('payment_vouchers.create');

        $purchase = $request->integer('purchase') ? Purchase::query()->visibleTo($request->user())->find($request->integer('purchase')) : null;
        $supplier = $purchase->supplier ?? ($request->integer('supplier') ? Supplier::query()->find($request->integer('supplier')) : null);

        $voucher = new PaymentVoucher([
            'voucher_date' => today(),
            'amount' => $purchase?->remaining(),
            'payment_method_id' => Lookup::options(LookupType::PaymentMethod)->first()?->id,
        ]);
        $voucher->supplier_id = $supplier->id ?? 0;

        return view('payment-vouchers.form', [
            'voucher' => $voucher,
            'supplier' => $supplier,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en', 'type']),
            'openPurchases' => $supplier ? $this->openPurchases($request->user(), $supplier->id) : new Collection,
            'allocations' => old('allocations', $purchase ? [$purchase->id => $purchase->remaining()] : []),
            'methods' => Lookup::options(LookupType::PaymentMethod),
        ]);
    }

    public function store(SavePaymentVoucherRequest $request, SavePaymentVoucher $save, AllocatePaymentVoucher $allocate): RedirectResponse
    {
        // One transaction: a refused allocation also gives the voucher number back.
        $voucher = DB::transaction(function () use ($request, $save, $allocate) {
            $voucher = $save->handle($request->validated(), $request->user());
            $allocate->handle($voucher, (array) $request->validated('allocations', []), $request->user());

            return $voucher;
        });

        return redirect()->route('payment-vouchers.show', $voucher)->with('success', __('app.saved'));
    }

    public function show(Request $request, PaymentVoucher $paymentVoucher): View
    {
        Gate::authorize('payment_vouchers.view');
        $this->ensureVisible($request, $paymentVoucher);

        $paymentVoucher->load(['supplier', 'paymentMethod', 'canceller', 'creator', 'allocations.purchase.project']);

        return view('payment-vouchers.show', [
            'voucher' => $paymentVoucher,
            'openPurchases' => $paymentVoucher->isActive() ? $this->openPurchases($request->user(), $paymentVoucher->supplier_id, $paymentVoucher) : new Collection,
        ]);
    }

    public function edit(Request $request, PaymentVoucher $paymentVoucher): View|RedirectResponse
    {
        Gate::authorize('payment_vouchers.update');
        $this->ensureVisible($request, $paymentVoucher);

        if (! $paymentVoucher->isActive()) {
            return redirect()->route('payment-vouchers.show', $paymentVoucher)->with('error', __('resources.vouchers.cancelled_locked'));
        }

        return view('payment-vouchers.form', [
            'voucher' => $paymentVoucher,
            'supplier' => $paymentVoucher->supplier,
            'suppliers' => collect(),
            'openPurchases' => new Collection,
            'allocations' => [],
            'methods' => Lookup::options(LookupType::PaymentMethod),
        ]);
    }

    public function update(SavePaymentVoucherRequest $request, PaymentVoucher $paymentVoucher, SavePaymentVoucher $save): RedirectResponse
    {
        $this->ensureVisible($request, $paymentVoucher);

        $save->handle($request->validated(), $request->user(), $paymentVoucher);

        return redirect()->route('payment-vouchers.show', $paymentVoucher)->with('success', __('app.saved'));
    }

    /** Changes how the voucher is spread over the supplier's invoices. */
    public function allocate(Request $request, PaymentVoucher $paymentVoucher, AllocatePaymentVoucher $allocate): RedirectResponse
    {
        Gate::authorize('payment_vouchers.update');
        $this->ensureVisible($request, $paymentVoucher);

        $data = $request->validate([
            'allocations' => ['nullable', 'array', 'max:200'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        $allocate->handle($paymentVoucher, (array) ($data['allocations'] ?? []), $request->user());

        return redirect()->route('payment-vouchers.show', $paymentVoucher)->with('success', __('resources.vouchers.allocated_ok'));
    }

    public function cancel(CancelDocumentRequest $request, PaymentVoucher $paymentVoucher, CancelPaymentVoucher $cancel): RedirectResponse
    {
        $this->ensureVisible($request, $paymentVoucher);

        $cancel->handle($paymentVoucher, (string) $request->validated('reason'), $request->user());

        return redirect()->route('payment-vouchers.show', $paymentVoucher)->with('success', __('resources.vouchers.cancelled'));
    }

    public function attach(StoreAttachmentsRequest $request, PaymentVoucher $paymentVoucher, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('payment_vouchers.update');
        $this->ensureVisible($request, $paymentVoucher);

        $attach->handle($paymentVoucher, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    /**
     * Purchase invoices of the supplier with something left to pay, plus those this voucher pays.
     *
     * @return Collection<int, Purchase>
     */
    private function openPurchases(User $user, int $supplierId, ?PaymentVoucher $voucher = null): Collection
    {
        $paidByVoucher = $voucher?->allocations->pluck('purchase_id')->all() ?? [];

        return Purchase::query()->visibleTo($user)->with('project')
            ->where('supplier_id', $supplierId)
            ->where(fn ($query) => $query->whereColumn('paid_amount', '<', 'total')->orWhereIn('id', $paidByVoucher))
            ->orderBy('purchase_date')
            ->get();
    }

    private function ensureVisible(Request $request, PaymentVoucher $voucher): void
    {
        abort_unless($voucher->isVisibleTo($request->user()), 404);
    }
}
