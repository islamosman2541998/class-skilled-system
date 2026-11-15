<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A tax invoice (spec §8.10). Drafts have no number and can be edited or deleted; an issued
 * invoice is never edited, only cancelled with a reason and possibly replaced.
 *
 * @property int $id
 * @property string|null $number
 * @property int $project_id
 * @property int $client_id
 * @property int|null $installment_id
 * @property int|null $claim_id
 * @property int|null $replaces_id
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property InvoiceStatus $status
 * @property string $vat_rate
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total
 * @property string $paid_amount
 * @property string|null $notes
 * @property string|null $terms_ar
 * @property string|null $terms_en
 * @property Carbon|null $issued_at
 * @property int|null $issued_by
 * @property string|null $cancel_reason
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property int|null $created_by
 * @property-read Project $project
 * @property-read Client $client
 * @property-read ProjectInstallment|null $installment
 * @property-read Invoice|null $replaces
 * @property-read Collection<int, InvoiceItem> $items
 */
class Invoice extends Model implements GuardsDeletion
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    protected $fillable = ['project_id', 'issue_date', 'due_date', 'notes', 'terms_ar', 'terms_en'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<ProjectInstallment, $this> */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(ProjectInstallment::class);
    }

    /** @return BelongsTo<Claim, $this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'replaces_id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<ReceiptAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function remaining(): string
    {
        return bcsub((string) $this->total, (string) $this->paid_amount, 2);
    }

    /** Issued or partly paid with the due date passed (not a stored status). */
    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid], true) && $this->due_date->lt(today());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        self::constrainOverdue($query);
    }

    /** The overdue condition on any invoice query (tables, dashboards, notifications). */
    public static function constrainOverdue(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate($query->qualifyColumn('due_date'), '<', today());
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), InvoiceStatus::activeValues());
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
        return $this->isDraft() ? null : __('finance.invoices.only_drafts_delete');
    }
}
