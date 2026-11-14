<?php

namespace App\Models;

use App\Enums\StageStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A stage of a project (spec §8.7).
 *
 * @property int $id
 * @property int $project_id
 * @property string $name_ar
 * @property string|null $name_en
 * @property-read string $name
 * @property int $sort_order
 * @property string $weight
 * @property Carbon|null $planned_start
 * @property Carbon|null $planned_end
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_end
 * @property StageStatus $status
 * @property string $progress
 * @property string|null $notes
 * @property-read Project $project
 */
class ProjectStage extends Model implements HasMedia
{
    use HasAttachments, HasLocalizedName, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'name_ar', 'name_en', 'sort_order', 'weight', 'planned_start', 'planned_end', 'actual_start',
        'actual_end', 'status', 'progress', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => StageStatus::class,
            'weight' => 'decimal:2',
            'progress' => 'decimal:2',
            'planned_start' => 'date',
            'planned_end' => 'date',
            'actual_start' => 'date',
            'actual_end' => 'date',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isLate(): bool
    {
        return $this->planned_end !== null && $this->planned_end->lt(today()) && $this->status !== StageStatus::Done;
    }

    /** Files of a stage follow the project's permissions and visibility. */
    public function attachmentPermission(): string
    {
        return 'projects';
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->project->isVisibleTo($user);
    }

    public function activityModule(): string
    {
        return 'projects';
    }
}
