<?php

namespace App\Models;

use App\Enums\InstallmentDueType;
use App\Enums\InstallmentStatus;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One line of a project's payment schedule (spec §8.8).
 *
 * @property int $id
 * @property int $project_id
 * @property string $label_ar
 * @property string|null $label_en
 * @property string|null $percent
 * @property string $amount
 * @property InstallmentDueType $due_type
 * @property Carbon|null $due_date
 * @property int|null $stage_id
 * @property InstallmentStatus $status
 * @property int|null $variation_id
 * @property int $sort_order
 * @property-read Project $project
 * @property-read ProjectStage|null $stage
 */
class ProjectInstallment extends Model
{
    use LogsModelActivity, SoftDeletes;

    protected $fillable = ['label_ar', 'label_en', 'percent', 'amount', 'due_type', 'due_date', 'stage_id', 'status', 'variation_id', 'sort_order'];

    protected function casts(): array
    {
        return [
            'due_type' => InstallmentDueType::class,
            'status' => InstallmentStatus::class,
            'due_date' => 'date',
            'percent' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ProjectStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    public function label(?string $language = null): string
    {
        return ($language ?? app()->getLocale()) === 'en' && filled($this->label_en) ? (string) $this->label_en : $this->label_ar;
    }

    public function activityModule(): string
    {
        return 'projects';
    }
}
