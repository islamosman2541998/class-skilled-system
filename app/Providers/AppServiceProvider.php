<?php

namespace App\Providers;

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
    }

    private function registerNavigation(): void
    {
        Menu::register('admin', ['label' => 'users.users', 'icon' => 'users', 'route' => 'users.index', 'permission' => 'users.view']);
        Menu::register('admin', ['label' => 'users.roles', 'icon' => 'shield-lock', 'route' => 'roles.index', 'permission' => 'roles.view']);
    }
}
