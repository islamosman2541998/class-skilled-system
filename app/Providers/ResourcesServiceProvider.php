<?php

namespace App\Providers;

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierController;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Search\PaymentVoucherSearchProvider;
use App\Search\PurchaseSearchProvider;
use App\Search\SearchRegistry;
use App\Search\SupplierSearchProvider;
use App\Services\Finance\ProjectLedger;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\TrashRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Stage 5 (resources): suppliers, purchases, subcontracts, payment vouchers, labour and equipment
 * join the earlier registries here.
 */
class ResourcesServiceProvider extends ServiceProvider
{
    /** Static registries live for the whole PHP process; they are filled once, not on every boot. */
    private static bool $registered = false;

    public function boot(): void
    {
        // The search registry is a container singleton, so it is filled on every boot.
        $this->app->make(SearchRegistry::class)->register(new SupplierSearchProvider);
        $this->app->make(SearchRegistry::class)->register(new PurchaseSearchProvider);
        $this->app->make(SearchRegistry::class)->register(new PaymentVoucherSearchProvider);

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PermissionRegistry::register('suppliers');

        Menu::register('resources', ['label' => 'resources.suppliers.suppliers', 'icon' => 'truck', 'route' => 'suppliers.index', 'permission' => 'suppliers.view']);
        TrashRegistry::register(Supplier::class);

        PermissionRegistry::register('purchases');
        Menu::register('resources', ['label' => 'resources.purchases.purchases', 'icon' => 'receipt', 'route' => 'purchases.index', 'permission' => 'purchases.view']);
        TrashRegistry::register(Purchase::class);
        Supplier::$linkedRelations[] = 'purchases';
        Project::$linkedRelations[] = 'purchases';
        ProjectController::$tabPermissions['purchases'] = ['purchases.view'];
        SupplierController::$tabs['purchases'] = ['purchases.view'];
        SupplierController::$figureSources['purchases_total'] = [
            'permission' => 'purchases.view',
            'value' => fn (Supplier $supplier) => money((string) $supplier->purchases()->visibleTo(auth()->user())->sum('total')),
        ];

        PermissionRegistry::register('subcontracts');
        Menu::register('resources', ['label' => 'resources.subcontracts.subcontracts', 'icon' => 'file-text', 'route' => 'subcontracts.index', 'permission' => 'subcontracts.view']);
        TrashRegistry::register(Subcontract::class);
        Supplier::$linkedRelations[] = 'subcontracts';
        Project::$linkedRelations[] = 'subcontracts';
        SupplierController::$tabs['subcontracts'] = ['subcontracts.view'];
        PurchaseController::$projectViews[] = 'subcontracts.partials.project-panel';

        PermissionRegistry::register('payment_vouchers', ['view', 'create', 'update', 'export', 'print', 'cancel']);
        Menu::register('finance', ['label' => 'resources.vouchers.vouchers', 'icon' => 'cash-banknote', 'route' => 'payment-vouchers.index', 'permission' => 'payment_vouchers.view']);
        Supplier::$linkedRelations[] = 'paymentVouchers';
        SupplierController::$tabs['payment_vouchers'] = ['payment_vouchers.view'];
        SupplierController::$tabs['projects'] = ['purchases.view'];
        PurchaseController::$showViews[] = 'purchases.partials.payments';
        SupplierController::$figureSources['paid'] = [
            'permission' => 'payment_vouchers.view',
            'value' => fn (Supplier $supplier) => money((string) $supplier->purchases()->visibleTo(auth()->user())->sum('paid_amount')),
        ];
        SupplierController::$figureSources['due'] = [
            'permission' => 'purchases.view',
            'value' => fn (Supplier $supplier) => money(bcsub(
                (string) $supplier->purchases()->visibleTo(auth()->user())->sum('total'),
                (string) $supplier->purchases()->visibleTo(auth()->user())->sum('paid_amount'),
                2,
            )),
        ];
        SupplierController::$figureSources['projects'] = [
            'permission' => 'purchases.view',
            'value' => fn (Supplier $supplier) => (string) Project::query()->visibleTo(auth()->user())
                ->where(fn ($query) => $query->whereIn('id', $supplier->purchases()->select('project_id'))->orWhereIn('id', $supplier->subcontracts()->select('project_id')))
                ->count(),
        ];

        // Project cost: purchase invoices before VAT (spec §8.16).
        ProjectLedger::$costSources['purchases'] = fn (Project $project) => (string) Purchase::query()->where('project_id', $project->id)->sum('subtotal');
    }
}
