<?php

namespace App\Providers;

use App\Http\Controllers\ProjectController;
use App\Models\Claim;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\User;
use App\Search\ClaimSearchProvider;
use App\Search\DailyReportSearchProvider;
use App\Search\SearchRegistry;
use App\Services\Dashboard\DashboardRegistry;
use App\Services\Site\ReportCalendar;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\ReportRegistry;
use App\Support\TrashRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Stage 4 (site): daily reports and progress claims join the earlier registries here.
 */
class SiteServiceProvider extends ServiceProvider
{
    /** Static registries live for the whole PHP process; they are filled once, not on every boot. */
    private static bool $registered = false;

    public function boot(): void
    {
        // The search registry is a container singleton, so it is filled on every boot.
        $this->app->make(SearchRegistry::class)->register(new DailyReportSearchProvider);
        $this->app->make(SearchRegistry::class)->register(new ClaimSearchProvider);

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PermissionRegistry::register('daily_reports');
        PermissionRegistry::register('claims', ['view', 'create', 'update', 'delete', 'export', 'print', 'approve']);

        Menu::register('projects', ['label' => 'site.reports.reports', 'icon' => 'clipboard-text', 'route' => 'daily-reports.index', 'permission' => 'daily_reports.view']);
        Menu::register('finance', ['label' => 'site.claims.claims', 'icon' => 'file-certificate', 'route' => 'claims.index', 'permission' => 'claims.view']);
        TrashRegistry::register(DailyReport::class);
        TrashRegistry::register(Claim::class);

        Project::$linkedRelations[] = 'dailyReports';
        Project::$linkedRelations[] = 'claims';
        ProjectController::$tabPermissions['daily_reports'] = ['daily_reports.view'];
        ProjectController::$tabPermissions['claims'] = ['claims.view'];
        ProjectController::$tabConditions['claims'] = fn (Project $project) => $project->isClaimsBilled();

        ReportRegistry::register('missing-reports', 'tables.reports.missing-reports-report', 'projects', ['daily_reports.view']);
        $this->registerDashboard();
    }

    /** Site figures on the dashboard (spec §9.1): working days of the last week without a daily report. */
    private function registerDashboard(): void
    {
        DashboardRegistry::register('reports_not_sent', 'daily_reports.view',
            fn (User $user) => ReportCalendar::missingQuery($user, today()->subDays(7), today()->subDay())->count(),
            fn (User $user) => ReportRegistry::allows($user, 'missing-reports')
                ? route('reports.show', ['missing-reports', 'from' => today()->subDays(7)->toDateString(), 'to' => today()->subDay()->toDateString()])
                : null,
            'projects');
    }
}
