<?php

namespace App\Providers;

use App\Enums\LookupType;
use App\Enums\NotificationType;
use App\Enums\SiteVisitStatus;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeadController;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\SiteVisit;
use App\Search\ClientSearchProvider;
use App\Search\LeadSearchProvider;
use App\Search\SearchRegistry;
use App\Search\SiteVisitSearchProvider;
use App\Services\DueTasks;
use App\Services\Notifier;
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
        TrashRegistry::register(SiteVisit::class);

        $this->registerLeads();
        $this->registerSiteVisits();
        self::registerDueTasks();

        $search = $this->app->make(SearchRegistry::class);
        $search->register(new ClientSearchProvider);
        $search->register(new LeadSearchProvider);
        $search->register(new SiteVisitSearchProvider);
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

    private function registerSiteVisits(): void
    {
        Client::$linkedRelations[] = 'siteVisits';
        Client::$visibilityRules[] = fn (Builder $query, $user) => $query->orWhereHas('siteVisits', fn (Builder $visits) => $visits->where('engineer_id', $user->id));
        Lead::$linkedRelations[] = 'siteVisits';
        LeadController::$headerActionViews[] = 'site-visits.partials.lead-action';
        LeadController::$showViews[] = 'site-visits.partials.lead-visits';
    }

    /** Periodic checks of the sales modules (no cron, spec §3.2). */
    public static function registerDueTasks(): void
    {
        DueTasks::register('visit-today', 60, function () {
            $notifier = app(Notifier::class);

            SiteVisit::query()->with('engineer')
                ->where('status', SiteVisitStatus::Scheduled)
                ->whereBetween('scheduled_at', [today(), today()->endOfDay()])
                ->each(fn (SiteVisit $visit) => $notifier->toUser(
                    $visit->engineer, NotificationType::VisitToday, $visit, ['number' => $visit->number],
                    route('site-visits.show', $visit), 'visit-'.today()->format('Y-m-d'),
                ));
        });
    }

    private function registerPermissions(): void
    {
        PermissionRegistry::register('clients');
        PermissionRegistry::register('leads');
        PermissionRegistry::register('site_visits');
    }

    private function registerMenu(): void
    {
        Menu::register('sales', ['label' => 'leads.leads', 'icon' => 'inbox', 'route' => 'leads.index', 'permission' => 'leads.view']);
        Menu::register('sales', ['label' => 'visits.visits', 'icon' => 'map-pin', 'route' => 'site-visits.index', 'permission' => 'site_visits.view']);
        Menu::register('sales', ['label' => 'visits.my_visits', 'icon' => 'calendar', 'route' => 'my-visits', 'permission' => 'site_visits.view']);
        Menu::register('sales', ['label' => 'clients.clients', 'icon' => 'users-group', 'route' => 'clients.index', 'permission' => 'clients.view']);
    }
}
