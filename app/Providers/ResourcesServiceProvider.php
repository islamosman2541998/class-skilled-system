<?php

namespace App\Providers;

use App\Models\Supplier;
use App\Search\SearchRegistry;
use App\Search\SupplierSearchProvider;
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

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PermissionRegistry::register('suppliers');

        Menu::register('resources', ['label' => 'resources.suppliers.suppliers', 'icon' => 'truck', 'route' => 'suppliers.index', 'permission' => 'suppliers.view']);
        TrashRegistry::register(Supplier::class);
    }
}
