<?php

namespace App\Http\Controllers;

use App\Actions\Resources\BillSubcontractPayment;
use App\Actions\Resources\SaveSubcontract;
use App\Enums\SupplierType;
use App\Http\Requests\SaveSubcontractRequest;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Subcontracts (spec §8.16): scope, value and payment lines; each line is billed as a purchase.
 */
class SubcontractController extends Controller
{
    public function index(): View
    {
        Gate::authorize('subcontracts.view');

        return view('subcontracts.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('subcontracts.create');

        $project = $request->integer('project') ? Project::query()->visibleTo($request->user())->find($request->integer('project')) : null;
        $subcontract = new Subcontract(['start_date' => today()]);
        $subcontract->forceFill(['project_id' => $project?->id, 'supplier_id' => $request->integer('supplier') ?: null]);

        return $this->form($request, $subcontract);
    }

    public function store(SaveSubcontractRequest $request, SaveSubcontract $save): RedirectResponse
    {
        $subcontract = $save->handle($request->validated(), $request->user());

        return redirect()->route('subcontracts.show', $subcontract)->with('success', __('app.saved'));
    }

    public function show(Request $request, Subcontract $subcontract): View
    {
        Gate::authorize('subcontracts.view');
        $this->ensureVisible($request, $subcontract);

        return view('subcontracts.show', ['subcontract' => $subcontract->load(['supplier', 'project', 'payments.purchase'])]);
    }

    public function edit(Request $request, Subcontract $subcontract): View
    {
        Gate::authorize('subcontracts.update');
        $this->ensureVisible($request, $subcontract);

        return $this->form($request, $subcontract->load('payments.purchase'));
    }

    public function update(SaveSubcontractRequest $request, Subcontract $subcontract, SaveSubcontract $save): RedirectResponse
    {
        $this->ensureVisible($request, $subcontract);

        $save->handle($request->validated(), $request->user(), $subcontract);

        return redirect()->route('subcontracts.show', $subcontract)->with('success', __('app.saved'));
    }

    public function bill(Request $request, Subcontract $subcontract, SubcontractPayment $payment, BillSubcontractPayment $bill): RedirectResponse
    {
        Gate::authorize('purchases.create');
        abort_unless($payment->subcontract_id === $subcontract->id, 404);
        $this->ensureVisible($request, $subcontract);

        try {
            $purchase = $bill->handle($payment, $request->user());
        } catch (DomainException $exception) {
            return redirect()->route('subcontracts.show', $subcontract)->with('error', $exception->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)->with('success', __('resources.subcontracts.billed'));
    }

    private function form(Request $request, Subcontract $subcontract): View
    {
        return view('subcontracts.form', [
            'subcontract' => $subcontract,
            'suppliers' => Supplier::query()->where('type', SupplierType::Subcontractor->value)
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $subcontract->supplier_id))
                ->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']),
            'projects' => Project::query()->visibleTo($request->user())->latest('id')->get(['id', 'number', 'title_ar', 'title_en']),
            'payments' => old('payments', $subcontract->exists
                ? $subcontract->payments->map(fn (SubcontractPayment $payment) => [
                    'id' => $payment->id, 'label_ar' => $payment->label_ar, 'label_en' => $payment->label_en,
                    'amount' => $payment->amount, 'due_date' => $payment->due_date?->format('Y-m-d'), 'billed' => $payment->purchase !== null,
                ])->all()
                : [['label_ar' => '', 'amount' => '']]),
        ]);
    }

    private function ensureVisible(Request $request, Subcontract $subcontract): void
    {
        abort_unless($subcontract->isVisibleTo($request->user()), 404);
    }
}
