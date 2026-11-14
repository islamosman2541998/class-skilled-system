<?php

namespace App\Providers;

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Search\InvoiceSearchProvider;
use App\Search\SearchRegistry;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\TrashRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Stage 3 (finance): the one place where finance modules join the earlier registries.
 */
class FinanceServiceProvider extends ServiceProvider
{
    /** Static registries live for the whole PHP process; they are filled once, not on every boot. */
    private static bool $registered = false;

    public function boot(): void
    {
        // The search registry is a container singleton, so it is filled on every boot.
        $this->app->make(SearchRegistry::class)->register(new InvoiceSearchProvider);

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PermissionRegistry::register('invoices', [...PermissionRegistry::ACTIONS, 'cancel']);
        PermissionRegistry::register('finance', ['costs', 'profitability']);

        Menu::register('finance', ['label' => 'finance.invoices.invoices', 'icon' => 'file-invoice', 'route' => 'invoices.index', 'permission' => 'invoices.view']);
        TrashRegistry::register(Invoice::class);

        Project::$linkedRelations[] = 'invoices';
        ProjectController::$tabPermissions['invoices'] = ['invoices.view'];
        Client::$linkedRelations[] = 'invoices';
        ClientController::$figureProviders['invoiced'] = fn (Client $client) => auth()->user()?->can('invoices.view')
            ? money((string) $client->invoices()->active()->visibleTo(auth()->user())->sum('total'))
            : '';
    }
}
