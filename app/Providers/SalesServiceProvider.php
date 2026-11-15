<?php

namespace App\Providers;

use App\Enums\LookupType;
use App\Enums\NotificationType;
use App\Enums\QuotationStatus;
use App\Enums\SiteVisitStatus;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\QuotationController;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\PriceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SiteVisit;
use App\Models\User;
use App\Search\ClientSearchProvider;
use App\Search\LeadSearchProvider;
use App\Search\QuotationSearchProvider;
use App\Search\SearchRegistry;
use App\Search\SiteVisitSearchProvider;
use App\Services\Dashboard\DashboardRegistry;
use App\Services\Dashboard\Period;
use App\Services\DueTasks;
use App\Services\Notifier;
use App\Settings\NotificationSettings;
use App\Support\LookupUsage;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\ReportRegistry;
use App\Support\TrashRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // The website API: 30 requests a minute per address (spec §10, §12).
        RateLimiter::for('website', fn (Request $request) => Limit::perMinute(30)->by((string) $request->ip()));

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

        ClientController::$figureProviders['leads'] = fn (Client $client) => (string) $client->leads()->visibleTo(auth()->user())->count();
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
        LeadController::$indexActionViews[] = 'leads.partials.fetch-website';
        LeadController::$showViews[] = 'quotations.partials.lead-quotations';
        ClientController::$actionViews[] = 'quotations.partials.client-action';
        QuotationController::$actionViews[] = 'quotations.partials.actions';
        QuotationController::$primaryActionViews[] = 'quotations.partials.share-actions';
        ClientController::$figureProviders['quotations'] = fn (Client $client) => (string) $client->quotations()->visibleTo(auth()->user())->count();
        ClientController::$figureProviders['accepted_total'] = fn (Client $client) => money($client->quotations()->visibleTo(auth()->user())->where('status', QuotationStatus::Accepted)->sum('total'));

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

        // A sent quotation without an answer after the configured days (spec §5.5).
        DueTasks::register('quotation-no-response', 60, function () {
            $days = (int) (app(NotificationSettings::class)->rule(NotificationType::QuotationNoResponse)['days'] ?? 3);
            $notifier = app(Notifier::class);

            Quotation::query()->with('assignee')
                ->where('status', QuotationStatus::Sent)->whereNull('superseded_at')
                ->where('sent_at', '<=', now()->subDays($days))
                ->each(fn (Quotation $q) => $notifier->toUser(
                    $q->assignee, NotificationType::QuotationNoResponse, $q, ['number' => $q->number],
                    route('quotations.show', $q), 'no-response',
                ));
        });

        // A draft or sent quotation whose validity ends within the configured days.
        DueTasks::register('quotation-expiring', 60, function () {
            $days = (int) (app(NotificationSettings::class)->rule(NotificationType::QuotationExpiring)['days'] ?? 3);
            $notifier = app(Notifier::class);

            Quotation::query()->with('assignee')
                ->whereIn('status', [QuotationStatus::Draft, QuotationStatus::Sent])->whereNull('superseded_at')
                ->whereBetween('valid_until', [today(), today()->addDays($days)])
                ->each(fn (Quotation $q) => $notifier->toUser(
                    $q->assignee, NotificationType::QuotationExpiring, $q,
                    ['number' => $q->number, 'date' => fmt_date($q->valid_until)],
                    route('quotations.show', $q), 'expiring-'.$q->valid_until->format('Y-m-d'),
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

        ReportRegistry::register('sales', 'tables.reports.sales-performance-report', 'sales', ['leads.view', 'quotations.view']);
        $this->registerDashboard();
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

    /** Sales and visit figures on the dashboard (spec §9.1). */
    private function registerDashboard(): void
    {
        DashboardRegistry::register('new_leads', 'leads.view',
            fn (User $user, Period $period) => Lead::query()->visibleTo($user)
                ->where('created_at', '>=', $period->from->toDateString().' 00:00:00')
                ->where('created_at', '<', $period->to->copy()->addDay()->toDateString().' 00:00:00')
                ->count(),
            fn (User $user, Period $period) => route('leads.index', $period->query()),
            'sales');
        DashboardRegistry::register('follow_ups_today', 'leads.view',
            fn (User $user) => Lead::constrainFollowUpToday(Lead::query()->visibleTo($user))->count(),
            fn () => route('leads.index', ['f' => ['follow_up_today' => '1']]),
            'sales');
        DashboardRegistry::register('follow_ups_overdue', 'leads.view',
            fn (User $user) => Lead::constrainOverdueFollowUp(Lead::query()->visibleTo($user))->count(),
            fn () => route('leads.index', ['f' => ['overdue' => '1']]),
            'sales');
        DashboardRegistry::register('quotations_waiting', 'quotations.view',
            fn (User $user) => Quotation::query()->visibleTo($user)->where('status', QuotationStatus::Sent->value)->count(),
            fn () => route('quotations.index', ['f' => ['status' => QuotationStatus::Sent->value]]),
            'sales');
        DashboardRegistry::register('quotations_expiring', 'quotations.view',
            fn (User $user) => Quotation::constrainExpiring(Quotation::query()->visibleTo($user))->count(),
            fn () => route('quotations.index', ['f' => ['expiring' => '1']]),
            'sales');
        // Accepted ÷ (sent + accepted + rejected) of the period, as in the sales report.
        DashboardRegistry::register('conversion_rate', 'quotations.view',
            function (User $user, Period $period) {
                $counts = Quotation::query()->visibleTo($user)->whereNull('superseded_at')
                    ->whereDate('issue_date', '>=', $period->from->toDateString())
                    ->whereDate('issue_date', '<=', $period->to->toDateString())
                    ->whereIn('status', [QuotationStatus::Sent->value, QuotationStatus::Accepted->value, QuotationStatus::Rejected->value])
                    ->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
                $decided = (int) $counts->sum();

                return $decided === 0 ? null : bcdiv(bcmul((string) ($counts[QuotationStatus::Accepted->value] ?? 0), '100', 4), (string) $decided, 2);
            },
            fn (User $user, Period $period) => ReportRegistry::allows($user, 'sales') ? route('reports.show', ['sales', ...$period->query()]) : null,
            'sales', 'percent');
        DashboardRegistry::register('visits_today', 'site_visits.view',
            fn (User $user) => SiteVisit::query()->visibleTo($user)->whereDate('scheduled_at', today())->where('status', '!=', SiteVisitStatus::Cancelled->value)->count(),
            fn () => route('site-visits.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()]),
            'projects');
    }
}
