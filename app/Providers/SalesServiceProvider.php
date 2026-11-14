<?php

namespace App\Providers;

use App\Enums\LookupType;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeadController;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Search\ClientSearchProvider;
use App\Search\LeadSearchProvider;
use App\Search\SearchRegistry;
use App\Support\LookupUsage;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\TrashRegistry;
use Illuminate\Database\Eloquent\Builder;
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
        TrashRegistry::register(Lead::class);

        $this->registerLeads();

        $search = $this->app->make(SearchRegistry::class);
        $search->register(new ClientSearchProvider);
        $search->register(new LeadSearchProvider);
    }

    private function registerLeads(): void
    {
        Client::$linkedRelations[] = 'leads';
        Client::$visibilityRules[] = fn (Builder $query, $user) => $query->orWhereHas('leads', fn (Builder $leads) => $leads->where('assigned_to', $user->id));

        ClientController::$figureProviders['leads'] = fn (Client $client) => (string) $client->leads()->count();
        ClientController::$actionViews[] = 'leads.partials.client-action';
        LeadController::$showViews[] = 'leads.partials.follow-ups';

        $usedByLeads = fn (string $column) => fn (Lookup $lookup) => Lead::withTrashed()->where($column, $lookup->id)->exists();
        LookupUsage::register(LookupType::Service, $usedByLeads('service_id'));
        LookupUsage::register(LookupType::LeadStatus, $usedByLeads('status_id'));
        LookupUsage::register(LookupType::LeadSource, $usedByLeads('source_id'));
        LookupUsage::register(LookupType::RejectionReason, $usedByLeads('rejection_reason_id'));
        LookupUsage::register(LookupType::Emirate, $usedByLeads('emirate_id'));
        LookupUsage::register(LookupType::Area, $usedByLeads('area_id'));

        $usedByClients = fn (string $column) => fn (Lookup $lookup) => Client::withTrashed()->where($column, $lookup->id)->exists();
        LookupUsage::register(LookupType::LeadSource, $usedByClients('source_id'));
        LookupUsage::register(LookupType::Emirate, $usedByClients('emirate_id'));
        LookupUsage::register(LookupType::Area, $usedByClients('area_id'));
    }

    private function registerPermissions(): void
    {
        PermissionRegistry::register('clients');
        PermissionRegistry::register('leads');
    }

    private function registerMenu(): void
    {
        Menu::register('sales', ['label' => 'leads.leads', 'icon' => 'inbox', 'route' => 'leads.index', 'permission' => 'leads.view']);
        Menu::register('sales', ['label' => 'clients.clients', 'icon' => 'users-group', 'route' => 'clients.index', 'permission' => 'clients.view']);
    }
}
