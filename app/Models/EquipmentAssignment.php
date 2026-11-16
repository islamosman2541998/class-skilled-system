<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use App\Services\Finance\ProjectLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Equipment on a project for a period (spec §8.17): days × daily cost, stored.
 *
 * @property int $id
 * @property int $equipment_id
 * @property int $project_id
 * @property Carbon $date_from
 * @property Carbon $date_to
 * @property string $daily_cost
 * @property int $days
 * @property string $cost
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Equipment $equipment
 * @property-read Project $project
 */
class EquipmentAssignment extends Model
{
    use LogsModelActivity, SoftDeletes;

    protected $fillable = ['date_from', 'date_to', 'daily_cost', 'notes'];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'daily_cost' => 'decimal:2',
            'days' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    /** The project cost follows every save, delete and restore (old and new project). */
    protected static function booted(): void
    {
        $refresh = function (EquipmentAssignment $assignment) {
            $ids = array_unique(array_filter([$assignment->project_id, $assignment->getOriginal('project_id')]));

            foreach (Project::withTrashed()->whereIn('id', $ids)->get() as $project) {
                ProjectLedger::refresh($project);
            }
        };

        static::saved($refresh);
        static::deleted($refresh);
        static::restored($refresh);
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class)->withTrashed();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Visible when the project is visible (spec §6).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->seesAllRecords()) {
            $query->whereHas('project', fn (Builder $projects) => $projects->visibleTo($user));
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->seesAllRecords() || $this->project->isVisibleTo($user);
    }
}
