<?php

namespace App\Providers;

use App\Enums\LookupType;
use App\Enums\NotificationType;
use App\Enums\QuotationStatus;
use App\Enums\SiteVisitStatus;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeadController;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\PriceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SiteVisit;
use App\Search\ClientSearchProvider;
use App\Search\LeadSearchProvider;
use App\Search\QuotationSearchProvider;
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
    /** Static registries live for the whole PHP process; they are filled once, not on every boot. */
    private static bool $registered = false;

    public function boot(): void
    {
        if (! self::$registered) {
            self::$registered = true;

            $this->registerPermissions();
            $this->registerMenu();

            TrashRegistry::register(Client::class);
            TrashRegistry::register(Lead::class);
            TrashRegistry::register(SiteVisit::class);
            TrashRegistry::register(PriceItem::class);
            TrashRegistry::register(Quotation::class);

            LookupUsage::register(LookupType::Service, fn (Lookup $lookup) => PriceItem::withTrashed()->where('service_id', $lookup->id)->exists());
            LookupUsage::register(LookupType::Unit, fn (Lookup $lookup) => PriceItem::withTrashed()->where('unit_id', $lookup->id)->exists());

            $this->registerLeads();
            $this->registerSiteVisits();
            $this->registerQuotations();
            self::registerDueTasks();
        }

        // The search registry is a container singleton, so it is filled on every boot.
        $search = $this->app->make(SearchRegistry::class);
        $search->register(new ClientSearchProvider);
        $search->register(new LeadSearchProvider);
        $search->register(new SiteVisitSearchProvider);
        $search->register(new QuotationSearchProvider);
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

    private function registerQuotations(): void
    {
        Client::$linkedRelations[] = 'quotations';
        Client::$visibilityRules[] = fn (Builder $query, $user) => $query->orWhereHas('quotations', fn (Builder $quotations) => $quotations->where('assigned_to', $user->id));
        Lead::$linkedRelations[] = 'quotations';
        LeadController::$headerActionViews[] = 'quotations.partials.lead-action';
        LeadController::$showViews[] = 'quotations.partials.lead-quotations';
        ClientController::$actionViews[] = 'quotations.partials.client-action';
        ClientController::$figureProviders['quotations'] = fn (Client $client) => (string) $client->quotations()->count();
        ClientController::$figureProviders['accepted_total'] = fn (Client $client) => money($client->quotations()->where('status', QuotationStatus::Accepted)->sum('total'));

        LookupUsage::register(LookupType::Service, fn (Lookup $lookup) => Quotation::withTrashed()->where('service_id', $lookup->id)->exists());
        LookupUsage::register(LookupType::Unit, fn (Lookup $lookup) => QuotationItem::query()->where('unit_id', $lookup->id)->exists());
        LookupUsage::register(LookupType::RejectionReason, fn (Lookup $lookup) => Quotation::withTrashed()->where('rejection_reason_id', $lookup->id)->exists());
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
        PermissionRegistry::register('quotations', [...PermissionRegistry::ACTIONS, 'approve']);
        PermissionRegistry::register('price_items', ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export']);
    }

    private function registerMenu(): void
    {
        Menu::register('sales', ['label' => 'leads.leads', 'icon' => 'inbox', 'route' => 'leads.index', 'permission' => 'leads.view']);
        Menu::register('sales', ['label' => 'visits.visits', 'icon' => 'map-pin', 'route' => 'site-visits.index', 'permission' => 'site_visits.view']);
        Menu::register('sales', ['label' => 'visits.my_visits', 'icon' => 'calendar', 'route' => 'my-visits', 'permission' => 'site_visits.view']);
        Menu::register('sales', ['label' => 'quotations.quotations', 'icon' => 'file-invoice', 'route' => 'quotations.index', 'permission' => 'quotations.view']);
        Menu::register('sales', ['label' => 'price_items.price_items', 'icon' => 'list-numbers', 'route' => 'price-items.index', 'permission' => 'price_items.view']);
        Menu::register('sales', ['label' => 'clients.clients', 'icon' => 'users-group', 'route' => 'clients.index', 'permission' => 'clients.view']);
    }
}
