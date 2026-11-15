<?php

namespace App\Providers;

use App\Actions\Projects\SaveProject;
use App\Enums\InstallmentDueType;
use App\Enums\InstallmentStatus;
use App\Enums\LookupType;
use App\Enums\NotificationType;
use App\Enums\ProjectStatus;
use App\Enums\StageStatus;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\QuotationController;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use App\Models\ProjectHandover;
use App\Models\ProjectInstallment;
use App\Models\ProjectStage;
use App\Models\StageTemplate;
use App\Models\User;
use App\Search\MaintenanceSearchProvider;
use App\Search\ProjectSearchProvider;
use App\Search\SearchRegistry;
use App\Services\DueTasks;
use App\Services\InstallmentSchedule;
use App\Services\Notifier;
use App\Services\ProjectProgress;
use App\Settings\NotificationSettings;
use App\Support\LookupUsage;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use App\Support\ReportRegistry;
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
        $this->app->make(SearchRegistry::class)->register(new MaintenanceSearchProvider);

        if (self::$registered) {
            return;
        }

        self::$registered = true;

        Menu::register('projects', ['label' => 'projects.projects', 'icon' => 'building-skyscraper', 'route' => 'projects.index', 'permission' => 'projects.view']);
        Menu::register('projects', ['label' => 'maintenance.maintenance', 'icon' => 'tool', 'route' => 'maintenance.index', 'permission' => 'maintenance.view']);
        TrashRegistry::register(Project::class);
        TrashRegistry::register(MaintenanceRequest::class);

        Project::$linkedRelations[] = 'maintenanceRequests';
        Client::$linkedRelations[] = 'projects';
        Client::$visibilityRules[] = fn (Builder $query, $user) => $query->orWhereHas('projects', fn (Builder $projects) => $projects->where(
            fn (Builder $inner) => $inner->where('manager_id', $user->id)->orWhereHas('members', fn (Builder $members) => $members->where('users.id', $user->id))
        ));
        ClientController::$figureProviders['projects'] = fn (Client $client) => (string) $client->projects()->visibleTo(auth()->user())->count();
        ClientController::$actionViews[] = 'projects.partials.client-action';
        QuotationController::$primaryActionViews[] = 'quotations.partials.convert-action';

        $usedBy = fn (string $column) => fn (Lookup $lookup) => Project::withTrashed()->where($column, $lookup->id)->exists();
        LookupUsage::register(LookupType::Service, $usedBy('service_id'));
        LookupUsage::register(LookupType::Emirate, $usedBy('emirate_id'));

        PermissionRegistry::register('projects', [...PermissionRegistry::ACTIONS, 'approve']);
        PermissionRegistry::register('maintenance', ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export']);

        ReportRegistry::register('late', 'tables.reports.late-work-report', 'projects', ['projects.view']);

        LookupUsage::register(LookupType::Service, fn (Lookup $lookup) => StageTemplate::withTrashed()->where('service_id', $lookup->id)->exists());

        // A new project starts with the stage template of its service (spec §8.7).
        SaveProject::$afterCreate[] = function (Project $project, User $by) {
            foreach (StageTemplate::forService($project->service_id) as $index => $template) {
                $project->stages()->create([
                    'name_ar' => $template->name_ar,
                    'name_en' => $template->name_en,
                    'weight' => $template->weight,
                    'sort_order' => $index + 1,
                    'status' => StageStatus::NotStarted,
                ]);
            }
        };

        // A stage reaching "done" makes its installments due at once (spec §8.8).
        ProjectProgress::$afterRefresh[] = fn (Project $project) => InstallmentSchedule::markDue($project);

        self::registerDueTasks();
    }

    /** Periodic checks of the project modules (no cron, spec §3.2). */
    public static function registerDueTasks(): void
    {
        // A stage past its planned end and not done: roles from the rule plus the project manager.
        DueTasks::register('stage-late', 1440, function () {
            $notifier = app(Notifier::class);

            ProjectStage::query()->with('project.manager')
                ->where('status', '!=', StageStatus::Done->value)
                ->whereNotNull('planned_end')->whereDate('planned_end', '<', today())
                ->whereHas('project')
                ->each(function (ProjectStage $stage) use ($notifier) {
                    $project = $stage->project;
                    $params = ['number' => $project->number];
                    $url = route('projects.show', [$project, 'tab' => 'stages']);

                    $notifier->toRoles(NotificationType::StageLate, $project, $params, $url, 'stage-'.$stage->id);
                    $notifier->toUser($project->manager, NotificationType::StageLate, $project, $params, $url, 'stage-'.$stage->id.'-manager');
                });
        });

        // A running project whose planned end is near: the manager is told once per planned date.
        DueTasks::register('project-ending', 1440, function () {
            $days = (int) (app(NotificationSettings::class)->rule(NotificationType::ProjectEnding)['days'] ?? 7);
            $notifier = app(Notifier::class);

            Project::query()->with('manager')
                ->whereIn('status', [ProjectStatus::NotStarted->value, ProjectStatus::InProgress->value, ProjectStatus::OnHold->value])
                ->whereBetween('planned_end_date', [today(), today()->addDays($days)])
                ->each(fn (Project $project) => $notifier->toUser(
                    $project->manager, NotificationType::ProjectEnding, $project,
                    ['number' => $project->number, 'date' => fmt_date($project->planned_end_date)],
                    route('projects.show', $project), 'ending-'.$project->planned_end_date->format('Y-m-d'),
                ));
        });

        // A warranty ending within the rule days: the configured roles, once per end date.
        DueTasks::register('warranty-ending', 1440, function () {
            $days = (int) (app(NotificationSettings::class)->rule(NotificationType::WarrantyEnding)['days'] ?? 30);
            $notifier = app(Notifier::class);

            ProjectHandover::query()->with('project')
                ->whereBetween('warranty_end', [today(), today()->addDays($days)])
                ->whereHas('project')
                ->each(fn (ProjectHandover $handover) => $notifier->toRoles(
                    NotificationType::WarrantyEnding, $handover->project,
                    ['number' => $handover->project->number, 'date' => fmt_date($handover->warranty_end)],
                    route('projects.show', [$handover->project, 'tab' => 'handover']), 'warranty-'.$handover->warranty_end->format('Y-m-d'),
                ));
        });

        // Installments reaching their date become due; those due within the rule days are announced once.
        DueTasks::register('installments-due', 1440, function () {
            InstallmentSchedule::markDue();

            $days = (int) (app(NotificationSettings::class)->rule(NotificationType::InstallmentDue)['days'] ?? 3);
            $notifier = app(Notifier::class);

            ProjectInstallment::query()->with('project')
                ->whereIn('status', [InstallmentStatus::NotDue->value, InstallmentStatus::Due->value])
                ->where('due_type', InstallmentDueType::Date->value)
                ->whereBetween('due_date', [today(), today()->addDays($days)])
                ->whereHas('project')
                ->each(fn (ProjectInstallment $installment) => $notifier->toRoles(
                    NotificationType::InstallmentDue, $installment->project,
                    ['number' => $installment->project->number, 'date' => fmt_date($installment->due_date)],
                    route('projects.show', [$installment->project, 'tab' => 'payments']), 'due-'.$installment->id,
                ));
        });
    }
}
