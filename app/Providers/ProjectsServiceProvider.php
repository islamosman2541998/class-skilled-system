<?php

namespace App\Providers;

use App\Enums\LookupType;
use App\Http\Controllers\ClientController;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\StageTemplate;
use App\Search\ProjectSearchProvider;
use App\Search\SearchRegistry;
use App\Support\LookupUsage;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\TrashRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;

/**
 * Stage 2 (projects): the one place where project modules join the Stage 0 registries.
 */
class ProjectsServiceProvider extends ServiceProvider
{
    /** Static registries live for the whole PHP process; they are filled once, not on every boot. */
    private static bool $registered = false;

    public function boot(): void
    {
        // The search registry is a container singleton, so it is filled on every boot.
        $this->app->make(SearchRegistry::class)->register(new ProjectSearchProvider);

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        Menu::register('projects', ['label' => 'projects.projects', 'icon' => 'building-skyscraper', 'route' => 'projects.index', 'permission' => 'projects.view']);
        TrashRegistry::register(Project::class);

        Client::$linkedRelations[] = 'projects';
        Client::$visibilityRules[] = fn (Builder $query, $user) => $query->orWhereHas('projects', fn (Builder $projects) => $projects->where(
            fn (Builder $inner) => $inner->where('manager_id', $user->id)->orWhereHas('members', fn (Builder $members) => $members->where('users.id', $user->id))
        ));
        ClientController::$figureProviders['projects'] = fn (Client $client) => (string) $client->projects()->visibleTo(auth()->user())->count();
        ClientController::$actionViews[] = 'projects.partials.client-action';

        $usedBy = fn (string $column) => fn (Lookup $lookup) => Project::withTrashed()->where($column, $lookup->id)->exists();
        LookupUsage::register(LookupType::Service, $usedBy('service_id'));
        LookupUsage::register(LookupType::Emirate, $usedBy('emirate_id'));

        PermissionRegistry::register('projects', [...PermissionRegistry::ACTIONS, 'approve']);
        PermissionRegistry::register('maintenance', ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export']);

        LookupUsage::register(LookupType::Service, fn (Lookup $lookup) => StageTemplate::withTrashed()->where('service_id', $lookup->id)->exists());
    }
}
