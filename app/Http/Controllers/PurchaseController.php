<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Resources\SavePurchase;
use App\Enums\LookupType;
use App\Http\Requests\SavePurchaseRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Purchase invoices (spec §8.16). Payment vouchers add the allocations panel to the page.
 */
class PurchaseController extends Controller
{
    /**
     * Panels added by later tasks under the lines (allocations), each receiving $purchase.
     *
     * @var list<string>
     */
    public static array $showViews = [];

    /**
     * Panels added by later tasks to the project purchases tab (subcontracts), each receiving $project.
     *
     * @var list<string>
     */
    public static array $projectViews = [];

    public function index(): View
    {
        Gate::authorize('purchases.view');

        return view('purchases.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('purchases.create');

        $project = $request->integer('project') ? Project::query()->visibleTo($request->user())->find($request->integer('project')) : null;

        $purchase = new Purchase(['purchase_date' => today()]);
        $purchase->forceFill(['project_id' => $project?->id, 'supplier_id' => $request->integer('supplier') ?: null]);

        return $this->form($request, $purchase);
    }

    public function store(SavePurchaseRequest $request, SavePurchase $save): RedirectResponse
    {
        $purchase = $save->handle($request->validated(), $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', __('app.saved'));
    }

    public function show(Request $request, Purchase $purchase): View
    {
        Gate::authorize('purchases.view');
        $this->ensureVisible($request, $purchase);

        return view('purchases.show', ['purchase' => $purchase->load(['supplier', 'project', 'items.unit', 'creator'])]);
    }

    public function edit(Request $request, Purchase $purchase): View
    {
        Gate::authorize('purchases.update');
        $this->ensureVisible($request, $purchase);

        return $this->form($request, $purchase->load('items'));
    }

    public function update(SavePurchaseRequest $request, Purchase $purchase, SavePurchase $save): RedirectResponse
    {
        $this->ensureVisible($request, $purchase);

        $save->handle($request->validated(), $request->user(), $purchase);

        return redirect()->route('purchases.show', $purchase)->with('success', __('app.saved'));
    }

    public function destroy(Request $request, Purchase $purchase): RedirectResponse
    {
        Gate::authorize('purchases.delete');
        $this->ensureVisible($request, $purchase);

        if (($reason = $purchase->deletionBlockedReason($request->user())) !== null) {
            return back()->with('error', $reason);
        }

        $purchase->delete();

        return redirect()->route('purchases.index')->with('success', __('resources.purchases.deleted'));
    }

    public function attach(StoreAttachmentsRequest $request, Purchase $purchase, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('purchases.update');
        $this->ensureVisible($request, $purchase);

        $attach->handle($purchase, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function form(Request $request, Purchase $purchase): View
    {
        return view('purchases.form', [
            'purchase' => $purchase,
            'suppliers' => Supplier::query()->where('is_active', true)->orWhere('id', $purchase->supplier_id)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en', 'type']),
            'projects' => Project::query()->visibleTo($request->user())->latest('id')->get(['id', 'number', 'title_ar', 'title_en']),
            'items' => old('items', $purchase->exists
                ? $purchase->items->map->only(['description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price'])->all()
                : [['description_ar' => '', 'quantity' => '1', 'unit_price' => '0']]),
            'units' => Lookup::options(LookupType::Unit),
        ]);
    }

    private function ensureVisible(Request $request, Purchase $purchase): void
    {
        abort_unless($purchase->isVisibleTo($request->user()), 404);
    }
}
