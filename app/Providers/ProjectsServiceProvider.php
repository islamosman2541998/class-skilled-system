<?php

namespace App\Providers;

use App\Actions\Projects\SaveProject;
use App\Enums\LookupType;
use App\Enums\NotificationType;
use App\Enums\StageStatus;
use App\Http\Controllers\ClientController;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\StageTemplate;
use App\Models\User;
use App\Search\ProjectSearchProvider;
use App\Search\SearchRegistry;
use App\Services\DueTasks;
use App\Services\Notifier;
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
    }
}
