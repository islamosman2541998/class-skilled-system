<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Enums\LookupType;
use App\Enums\SupplierType;
use App\Http\Requests\SaveSupplierRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Lookup;
use App\Models\Supplier;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\View;

/**
 * Suppliers and subcontractors (spec §8.15). The page's tabs and figures are added by the
 * purchases, subcontracts and payment voucher modules.
 */
class SupplierController extends Controller
{
    /**
     * Tabs besides overview, attachments and activity: tab => permissions it needs. A tab shows
     * once its view exists.
     *
     * @var array<string, list<string>>
     */
    public static array $tabs = [];

    /**
     * Figures on the supplier page: key => closure returning the formatted value.
     *
     * @var array<string, array{permission: string, value: Closure(Supplier): string}>
     */
    public static array $figureSources = [];

    /**
     * Header buttons added by later tasks (new purchase, statement), each receiving $supplier.
     *
     * @var list<string>
     */
    public static array $actionViews = [];

    public function index(): View
    {
        Gate::authorize('suppliers.view');

        return view('suppliers.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('suppliers.create');

        return $this->form(new Supplier(['type' => $request->query('type') === 'subcontractor' ? SupplierType::Subcontractor : SupplierType::Supplier, 'is_active' => true]));
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        $supplier = new Supplier;
        $supplier->fill($this->values($request));
        $supplier->created_by = $request->user()->id;
        $supplier->save();

        return redirect()->route('suppliers.show', $supplier)->with('success', __('app.saved'));
    }

    public function show(Request $request, Supplier $supplier): View
    {
        Gate::authorize('suppliers.view');

        $tabs = collect(['overview' => []] + self::$tabs + ['attachments' => [], 'activity' => []])
            ->filter(fn (array $permissions, string $tab) => in_array($tab, ['overview', 'attachments', 'activity'], true) || ViewFactory::exists('suppliers.tabs.'.$tab))
            ->filter(fn (array $permissions) => collect($permissions)->every(fn (string $permission) => $request->user()->can($permission)))
            ->keys()
            ->mapWithKeys(fn (string $tab) => [$tab => __('resources.suppliers.tabs.'.$tab)])
            ->all();
        $active = array_key_exists((string) $request->query('tab'), $tabs) ? (string) $request->query('tab') : 'overview';

        $figures = collect(self::$figureSources)
            ->filter(fn (array $figure) => $request->user()->can($figure['permission']))
            ->map(fn (array $figure) => ($figure['value'])($supplier))
            ->all();

        return view('suppliers.show', ['supplier' => $supplier->load('specialty'), 'tabs' => $tabs, 'active' => $active, 'figures' => $figures]);
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('suppliers.update');

        return $this->form($supplier);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->fill($this->values($request))->save();

        return redirect()->route('suppliers.show', $supplier)->with('success', __('app.saved'));
    }

    public function attach(StoreAttachmentsRequest $request, Supplier $supplier, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('suppliers.update');

        $attach->handle($supplier, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    /**
     * @return array<string, mixed>
     */
    private function values(SaveSupplierRequest $request): array
    {
        return [...$request->safe()->except('is_active'), 'is_active' => $request->boolean('is_active', true)];
    }

    private function form(Supplier $supplier): View
    {
        return view('suppliers.form', [
            'supplier' => $supplier,
            'specialties' => Lookup::options(LookupType::SupplierSpecialty),
        ]);
    }
}
