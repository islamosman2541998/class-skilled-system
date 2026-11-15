<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\DailyReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A daily site report (spec §8.13): one per project per day, written by the engineer, with the new
 * progress of the stages worked on and photos and video as attachments.
 *
 * @property int $id
 * @property int $project_id
 * @property Carbon $report_date
 * @property int|null $engineer_id
 * @property string $work_done
 * @property int $labour_count
 * @property string|null $obstacles
 * @property bool $has_obstacles
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Project $project
 * @property-read User|null $engineer
 */
class DailyReport extends Model implements HasMedia
{
    /** @use HasFactory<DailyReportFactory> */
    use HasAttachments, HasFactory, LogsModelActivity, SoftDeletes;

    protected $fillable = ['project_id', 'report_date', 'work_done', 'labour_count', 'obstacles', 'notes'];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'labour_count' => 'integer',
            'has_obstacles' => 'boolean',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    /** @return HasMany<DailyReportStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(DailyReportStage::class);
    }

    /**
     * Reports of the projects the user may see.
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
        return $user->seesAllRecords() || Project::query()->visibleTo($user)->whereKey($this->project_id)->exists();
    }

    public function attachmentPermission(): string
    {
        return 'daily_reports';
    }
}
