<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Enums\PurchaseStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use App\Services\Finance\ProjectLedger;
use Database\Factories\PurchaseFactory;
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
 * A supplier's invoice on a project (spec §8.16). Materials go straight to project cost; the
 * subtotal counts as cost and the VAT as input VAT. Paid from payment voucher allocations.
 *
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property int $project_id
 * @property int|null $subcontract_id
 * @property int|null $subcontract_payment_id
 * @property Carbon $purchase_date
 * @property string $supplier_invoice_no
 * @property string $vat_rate
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total
 * @property string $paid_amount
 * @property PurchaseStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Supplier $supplier
 * @property-read Project $project
 * @property-read Collection<int, PurchaseItem> $items
 */
class Purchase extends Model implements GuardsDeletion, HasMedia
{
    /** @use HasFactory<PurchaseFactory> */
    use HasAttachments, HasFactory, LogsModelActivity, SoftDeletes;

    protected $fillable = ['purchase_date', 'supplier_invoice_no', 'notes'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'status' => PurchaseStatus::class,
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    /** The project cost follows every save, delete and restore (old and new project). */
    protected static function booted(): void
    {
        $refresh = function (Purchase $purchase) {
            $ids = array_unique(array_filter([$purchase->project_id, $purchase->getOriginal('project_id')]));

            foreach (Project::withTrashed()->whereIn('id', $ids)->get() as $project) {
                ProjectLedger::refresh($project);
            }
        };

        static::saved($refresh);
        static::deleted($refresh);
        static::restored($refresh);

        // A deleted subcontract bill frees its payment line, so the line can be billed again.
        static::deleting(function (Purchase $purchase) {
            if ($purchase->subcontract_payment_id !== null) {
                $purchase->subcontract_payment_id = null;
                $purchase->saveQuietly();
            }
        });
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

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function remaining(): string
    {
        return bcsub((string) $this->total, (string) $this->paid_amount, 2);
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

    public function deletionBlockedReason(User $by): ?string
    {
        return bccomp((string) $this->paid_amount, '0', 2) > 0 ? __('resources.purchases.paid_locked') : null;
    }
}
