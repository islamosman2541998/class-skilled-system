<?php

namespace App\Models;

use App\Enums\SubcontractStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\SubcontractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A subcontract on a project (spec §8.16): scope, value before VAT and payment lines. Each line is
 * billed as a purchase invoice, so the cost and payments follow the purchases.
 *
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property int $project_id
 * @property string $scope
 * @property string $value
 * @property string $vat_rate
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property SubcontractStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Supplier $supplier
 * @property-read Project $project
 * @property-read Collection<int, SubcontractPayment> $payments
 */
class Subcontract extends Model implements HasMedia
{
    /** @use HasFactory<SubcontractFactory> */
    use HasAttachments, HasFactory, LogsModelActivity, SoftDeletes;

    protected $fillable = ['scope', 'value', 'start_date', 'end_date', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => SubcontractStatus::class,
            'value' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<SubcontractPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SubcontractPayment::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** Σ live purchase invoices of the subcontract, before VAT. */
    public function billed(): string
    {
        return bcadd((string) $this->purchases()->sum('subtotal'), '0', 2);
    }

    public function remaining(): string
    {
        return bcsub((string) $this->value, $this->billed(), 2);
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
