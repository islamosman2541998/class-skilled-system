<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\MaintenanceRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A maintenance request on a project (spec §8.18). Users of an "assigned only" role see the
 * requests assigned to them and those of the projects they manage or belong to.
 *
 * @property int $id
 * @property string $number
 * @property int $project_id
 * @property Carbon $request_date
 * @property string $description
 * @property bool $in_warranty
 * @property MaintenanceStatus $status
 * @property int|null $assigned_to
 * @property string $cost
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Project $project
 * @property-read User|null $assignee
 */
class MaintenanceRequest extends Model implements HasMedia
{
    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasAttachments, HasFactory, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'project_id', 'request_date', 'description', 'in_warranty', 'status', 'assigned_to', 'cost', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'in_warranty' => 'boolean',
            'status' => MaintenanceStatus::class,
            'cost' => 'decimal:2',
        ];
    }

    /** Inside warranty when the request date falls between the handover date and the warranty end. */
    public static function warrantyCovers(Project $project, Carbon $date): bool
    {
        return $project->handover?->isUnderWarranty($date) ?? false;
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllRecords()) {
            return;
        }

        $query->where(fn (Builder $inner) => $inner
            ->where($query->qualifyColumn('assigned_to'), $user->id)
            ->orWhereHas('project', fn (Builder $projects) => $projects->visibleTo($user)));
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->seesAllRecords()
            || $this->assigned_to === $user->id
            || $this->project->isVisibleTo($user);
    }

    public function attachmentPermission(): string
    {
        return 'maintenance';
    }
}
