<?php

namespace App\Providers;

use App\Models\Lookup;
use App\Models\User;
use App\Support\Menu;
use App\Support\TrashRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        // The system administrator role passes every permission check.
        Gate::before(fn (User $user) => $user->hasRole('super-admin') ? true : null);

        $this->registerNavigation();

        TrashRegistry::register(User::class);
        TrashRegistry::register(Lookup::class);
    }

    private function registerNavigation(): void
    {
        Menu::register('admin', ['label' => 'users.users', 'icon' => 'users', 'route' => 'users.index', 'permission' => 'users.view']);
        Menu::register('admin', ['label' => 'users.roles', 'icon' => 'shield-lock', 'route' => 'roles.index', 'permission' => 'roles.view']);
        Menu::register('admin', ['label' => 'settings.lists_title', 'icon' => 'list-details', 'route' => 'lookups.index', 'params' => ['type' => 'service'], 'permission' => 'lookups.view']);
        Menu::register('admin', ['label' => 'settings.groups.activity', 'icon' => 'history', 'route' => 'activity.index', 'permission' => 'activity.view']);
        Menu::register('admin', ['label' => 'settings.settings', 'icon' => 'settings', 'route' => 'settings.index', 'permission' => 'settings.manage']);
    }
}
