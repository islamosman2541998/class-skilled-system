<?php

namespace App\Models;

use App\Enums\VariationStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A variation order: work added (positive) or removed (negative) after the contract (spec §8.9).
 *
 * @property int $id
 * @property string $number
 * @property int $project_id
 * @property string $description
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total
 * @property VariationStatus $status
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property string|null $rejection_note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Project $project
 */
class Variation extends Model implements HasMedia
{
    use HasAttachments, LogsModelActivity, SoftDeletes;

    protected $fillable = ['description'];

    protected function casts(): array
    {
        return [
            'status' => VariationStatus::class,
            'decided_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<VariationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(VariationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isDecided(): bool
    {
        return $this->status !== VariationStatus::Proposed;
    }

    /** The client's approval file and other papers follow the project's permissions and visibility. */
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
