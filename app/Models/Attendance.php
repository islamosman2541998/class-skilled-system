<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One worker's day on a project (spec §8.17), with the wage of that day and its stored cost. A
 * worker has one row per day.
 *
 * @property int $id
 * @property int $project_id
 * @property int $worker_id
 * @property Carbon $work_date
 * @property AttendanceStatus $status
 * @property string $overtime_hours
 * @property string $daily_wage
 * @property string $cost
 * @property int|null $created_by
 * @property-read Project $project
 * @property-read Worker $worker
 */
class Attendance extends Model
{
    protected $table = 'attendances';

    protected $fillable = ['status', 'overtime_hours'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'status' => AttendanceStatus::class,
            'overtime_hours' => 'decimal:2',
            'daily_wage' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class)->withTrashed();
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
}
