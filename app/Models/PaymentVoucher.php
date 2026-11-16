<?php

namespace App\Models;

use App\Enums\PaymentVoucherStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A payment to a supplier or subcontractor, spread over their purchase invoices (spec §8.16).
 * Never deleted: a wrong voucher is cancelled with a reason and stays visible (§5.2).
 *
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property Carbon $voucher_date
 * @property string $amount
 * @property int $payment_method_id
 * @property string|null $reference
 * @property string|null $notes
 * @property PaymentVoucherStatus $status
 * @property string|null $cancel_reason
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property int|null $created_by
 * @property-read Supplier $supplier
 * @property-read Lookup $paymentMethod
 * @property-read Collection<int, PaymentAllocation> $allocations
 */
class PaymentVoucher extends Model implements HasMedia
{
    use HasAttachments, LogsModelActivity;

    protected $fillable = ['voucher_date', 'amount', 'payment_method_id', 'reference', 'notes'];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'amount' => 'decimal:2',
            'status' => PaymentVoucherStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /** @return BelongsTo<Lookup, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'payment_method_id');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isActive(): bool
    {
        return $this->status === PaymentVoucherStatus::Active;
    }

    public function allocated(): string
    {
        return bcadd((string) $this->allocations()->sum('amount'), '0', 2);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), PaymentVoucherStatus::Active->value);
    }

    /**
     * Users who see every record see all vouchers; others see a voucher when every purchase it
     * pays is on a project they see (a voucher with no allocations is for everyone with access).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->seesAllRecords()) {
            $query->whereDoesntHave('allocations.purchase', fn (Builder $purchases) => $purchases->whereNotIn('project_id', Project::query()->visibleTo($user)->select('projects.id')));
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->seesAllRecords() || self::query()->visibleTo($user)->whereKey($this->id)->exists();
    }
}
