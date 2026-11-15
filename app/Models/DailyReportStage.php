<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The progress a daily report set for one stage (spec §8.13).
 *
 * @property int $id
 * @property int $daily_report_id
 * @property int $project_stage_id
 * @property string $progress
 * @property-read DailyReport $report
 * @property-read ProjectStage $stage
 */
class DailyReportStage extends Model
{
    protected $fillable = ['project_stage_id', 'progress'];

    protected function casts(): array
    {
        return ['progress' => 'decimal:2'];
    }

    /** @return BelongsTo<DailyReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }

    /** @return BelongsTo<ProjectStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'project_stage_id');
    }
}
