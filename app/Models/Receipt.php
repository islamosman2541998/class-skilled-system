<?php

namespace App\Models;

use App\Enums\ReceiptStatus;
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
 * A receipt voucher (spec §8.11): money received from a client, allocated to one or more
 * invoices; the unallocated rest is an advance settled later. Cancelled with a reason, never deleted.
 *
 * @property int $id
 * @property string $number
 * @property int $client_id
 * @property int|null $project_id
 * @property Carbon $receipt_date
 * @property string $amount
 * @property int $payment_method_id
 * @property string|null $reference
 * @property Carbon|null $cheque_date
 * @property int $received_by
 * @property string|null $notes
 * @property ReceiptStatus $status
 * @property string|null $cancel_reason
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property int|null $created_by
 * @property-read Client $client
 * @property-read Project|null $project
 * @property-read Lookup $paymentMethod
 * @property-read User $receiver
 * @property-read Collection<int, ReceiptAllocation> $allocations
 */
class Receipt extends Model implements HasMedia
{
    use HasAttachments, LogsModelActivity;

    protected $fillable = ['receipt_date', 'amount', 'payment_method_id', 'reference', 'cheque_date', 'received_by', 'notes'];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'cheque_date' => 'date',
            'amount' => 'decimal:2',
            'status' => ReceiptStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'payment_method_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<ReceiptAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function isActive(): bool
    {
        return $this->status === ReceiptStatus::Active;
    }

    public function allocated(): string
    {
        return bcadd((string) $this->allocations()->sum('amount'), '0', 2);
    }

    /** The advance part not yet allocated to an invoice. */
    public function unallocated(): string
    {
        return bcsub((string) $this->amount, $this->allocated(), 2);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ReceiptStatus::Active->value);
    }

    /**
     * Visible when its project is visible, or for a receipt without a project when its client is (spec §6).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllRecords()) {
            return;
        }

        $query->where(fn (Builder $inner) => $inner
            ->whereHas('project', fn (Builder $projects) => $projects->visibleTo($user))
            ->orWhere(fn (Builder $general) => $general->whereNull('project_id')->whereHas('client', fn (Builder $clients) => $clients->visibleTo($user))));
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->seesAllRecords()) {
            return true;
        }

        return $this->project !== null ? $this->project->isVisibleTo($user) : $this->client->isVisibleTo($user);
    }

    public function attachmentPermission(): string
    {
        return 'receipts';
    }
}
