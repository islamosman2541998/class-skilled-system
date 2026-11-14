<?php

namespace App\Actions\Projects;

use App\Enums\DocumentType;
use App\Enums\NotificationType;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifier;
use App\Services\NumberGenerator;
use App\Settings\FinanceSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a project. The contract value is always original + approved variations;
 * a posted value is ignored. New managers and team members are notified (not the actor).
 */
class SaveProject
{
    /** @var list<\Closure(Project, User): void> Hooks run after a project is created (stage template copy …). */
    public static array $afterCreate = [];

    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly FinanceSettings $finance,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated SaveProjectRequest data
     * @param  array<string, mixed>  $system  attributes set by the system (quotation link, values from a quotation)
     */
    public function handle(array $data, User $by, ?Project $project = null, array $system = []): Project
    {
        $creating = $project === null;
        $project ??= new Project;
        $previousManager = $project->manager_id;
        $previousMembers = $creating ? [] : $project->members()->pluck('users.id')->all();

        $project = DB::transaction(function () use ($data, $by, $project, $creating, $system) {
            $project->fill(Arr::only($data, $project->getFillable()));

            if ($creating) {
                $project->number = $this->numbers->next(DocumentType::Project);
                $project->status = $data['status'] ?? ProjectStatus::NotStarted;
                $project->vat_rate = $system['vat_rate'] ?? $this->finance->vat_rate;
                $project->created_by = $by->id;
            }

            foreach ($system as $key => $value) {
                $project->{$key} = $value;
            }

            $project->contract_value = bcadd((string) $project->original_value, (string) ($project->variations_total ?? '0'), 2);
            $project->save();

            if (array_key_exists('members', $data) || $creating) {
                $project->members()->sync(array_values(array_unique(array_map('intval', (array) ($data['members'] ?? [])))));
            }

            if ($creating) {
                foreach (self::$afterCreate as $hook) {
                    $hook($project, $by);
                }
            }

            return $project;
        });

        $this->notifyNewPeople($project, $by, $previousManager, $previousMembers);

        return $project;
    }

    /**
     * @param  list<int>  $previousMembers
     */
    private function notifyNewPeople(Project $project, User $by, ?int $previousManager, array $previousMembers): void
    {
        $ids = $project->members()->pluck('users.id')->all();
        $new = array_diff($ids, $previousMembers);

        if ($project->manager_id !== $previousManager) {
            $new[] = $project->manager_id;
        }

        foreach (User::query()->whereIn('id', array_unique($new))->whereKeyNot($by->id)->get() as $user) {
            $this->notifier->toUser($user, NotificationType::Assigned, $project, ['number' => $project->number], route('projects.show', $project));
        }
    }
}
