<?php

namespace App\Providers;

use App\Models\Client;
use App\Search\ClientSearchProvider;
use App\Search\SearchRegistry;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\TrashRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Stage 1 (sales): the one place where sales modules join the Stage 0 registries.
 */
class SalesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPermissions();
        $this->registerMenu();

        TrashRegistry::register(Client::class);

        $search = $this->app->make(SearchRegistry::class);
        $search->register(new ClientSearchProvider);
    }

    private function registerPermissions(): void
    {
        PermissionRegistry::register('clients');
    }

    private function registerMenu(): void
    {
        Menu::register('sales', ['label' => 'clients.clients', 'icon' => 'users-group', 'route' => 'clients.index', 'permission' => 'clients.view']);
    }
}
