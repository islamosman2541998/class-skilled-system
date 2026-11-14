<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * Handover of a project and its warranty (spec §8.18). The signed record is an attachment.
 *
 * @property int $id
 * @property int $project_id
 * @property Carbon $handover_date
 * @property int $warranty_months
 * @property Carbon $warranty_end
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Project $project
 */
class ProjectHandover extends Model implements HasMedia
{
    use HasAttachments, LogsModelActivity;

    protected $fillable = ['handover_date', 'warranty_months', 'warranty_end', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'handover_date' => 'date',
            'warranty_end' => 'date',
            'warranty_months' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Month-end safe: 31 January + 1 month is the last day of February. */
    public static function warrantyEnd(Carbon $handover, int $months): Carbon
    {
        return $handover->copy()->addMonthsNoOverflow($months);
    }

    public function isUnderWarranty(Carbon $on): bool
    {
        return $on->betweenIncluded($this->handover_date, $this->warranty_end);
    }

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
