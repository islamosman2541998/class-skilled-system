<?php

namespace App\Providers;

use App\Http\Controllers\ProjectController;
use App\Models\DailyReport;
use App\Models\Project;
use App\Search\DailyReportSearchProvider;
use App\Search\SearchRegistry;
use App\Support\Menu;
use App\Support\PermissionRegistry;
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

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PermissionRegistry::register('daily_reports');

        Menu::register('projects', ['label' => 'site.reports.reports', 'icon' => 'clipboard-text', 'route' => 'daily-reports.index', 'permission' => 'daily_reports.view']);
        TrashRegistry::register(DailyReport::class);

        Project::$linkedRelations[] = 'dailyReports';
        ProjectController::$tabPermissions['daily_reports'] = ['daily_reports.view'];
    }
}
