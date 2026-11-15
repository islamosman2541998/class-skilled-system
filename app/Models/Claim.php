<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Enums\ClaimStatus;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ClaimFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A progress claim of a project billed by claims (spec §5.9, §8.14). Amounts are before VAT
 * except vat_amount and total; the number is reserved at submission.
 *
 * @property int $id
 * @property string|null $number
 * @property int $project_id
 * @property int $client_id
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property ClaimStatus $status
 * @property string $vat_rate
 * @property string $retention_percent
 * @property string $work_previous
 * @property string $work_current
 * @property string $work_cumulative
 * @property string $discount_amount
 * @property string $gross_amount
 * @property string $retention_amount
 * @property string $advance_recovery
 * @property string $net_amount
 * @property string $vat_amount
 * @property string $total
 * @property string|null $notes
 * @property string|null $return_note
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Project $project
 * @property-read Client $client
 * @property-read Collection<int, ClaimLine> $lines
 */
class Claim extends Model implements GuardsDeletion
{
    /** @use HasFactory<ClaimFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    protected $fillable = ['period_from', 'period_to', 'notes'];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'status' => ClaimStatus::class,
            'vat_rate' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'work_previous' => 'decimal:2',
            'work_current' => 'decimal:2',
            'work_cumulative' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'advance_recovery' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    /** @return HasMany<ClaimLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ClaimLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The latest invoice of the claim that is not cancelled.
     *
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->where('status', '!=', InvoiceStatus::Cancelled->value)->latestOfMany();
    }

    public function isDraft(): bool
    {
        return $this->status === ClaimStatus::Draft;
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
        return $this->isDraft() ? null : __('site.claims.only_drafts_delete');
    }
}
