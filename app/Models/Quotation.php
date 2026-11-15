<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\NotificationType;
use App\Enums\QuotationStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasRecordScope;
use App\Models\Concerns\LogsModelActivity;
use App\Settings\NotificationSettings;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A quotation (spec §8.5). Revisions share the root's number with a -R{n} suffix; a superseded
 * revision is read-only.
 *
 * @property int $id
 * @property string $number
 * @property int $revision
 * @property int|null $parent_id
 * @property Carbon|null $superseded_at
 * @property int $client_id
 * @property int|null $lead_id
 * @property int|null $service_id
 * @property string $title_ar
 * @property string|null $title_en
 * @property-read string $title
 * @property string|null $location
 * @property int|null $emirate_id
 * @property Carbon $issue_date
 * @property Carbon $valid_until
 * @property int $assigned_to
 * @property QuotationStatus $status
 * @property DiscountType|null $discount_type
 * @property string $discount_value
 * @property string $vat_rate
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $taxable_amount
 * @property string $vat_amount
 * @property string $total
 * @property string|null $terms_ar
 * @property string|null $terms_en
 * @property string|null $notes
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property int|null $rejection_reason_id
 * @property string|null $rejection_note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Client $client
 * @property-read Lead|null $lead
 * @property-read User $assignee
 */
class Quotation extends Model implements HasMedia
{
    /** @use HasFactory<QuotationFactory> */
    use HasAttachments, HasFactory, HasRecordScope, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'client_id', 'lead_id', 'service_id', 'title_ar', 'title_en', 'location', 'emirate_id', 'issue_date',
        'valid_until', 'assigned_to', 'discount_type', 'discount_value', 'terms_ar', 'terms_en', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'discount_type' => DiscountType::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'superseded_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'discount_value' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function assigneeColumn(): string
    {
        return 'assigned_to';
    }

    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->title_en) ? (string) $this->title_en : $this->title_ar;
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'service_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function emirate(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'emirate_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'rejection_reason_id');
    }

    /** @return BelongsTo<Quotation, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasOne<Project, $this> */
    public function project(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    /** @return HasMany<QuotationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<QuotationPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(QuotationPayment::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The state to show: a draft or sent quotation past its validity date is expired. */
    public function displayStatus(): QuotationStatus
    {
        if (in_array($this->status, [QuotationStatus::Draft, QuotationStatus::Sent], true) && $this->valid_until->lt(today())) {
            return QuotationStatus::Expired;
        }

        return $this->status;
    }

    /** Accepted, rejected and superseded quotations are read-only (spec §8.5). */
    public function isLocked(): bool
    {
        return $this->superseded_at !== null || in_array($this->status, [QuotationStatus::Accepted, QuotationStatus::Rejected], true);
    }

    /**
     * Filters by the shown state, so "expired" and "sent" never overlap.
     *
     * @param  Builder<Quotation>  $query
     */
    public static function whereDisplayStatus(Builder $query, string $status): void
    {
        $open = [QuotationStatus::Draft->value, QuotationStatus::Sent->value];

        match ($status) {
            QuotationStatus::Expired->value => $query->whereIn('status', $open)->whereDate('valid_until', '<', today()),
            QuotationStatus::Draft->value, QuotationStatus::Sent->value => $query->where('status', $status)->whereDate('valid_until', '>=', today()),
            default => $query->where('status', $status),
        };
    }

    /** The number of the original quotation, without a revision suffix. */
    public function baseNumber(): string
    {
        return (string) preg_replace('/-R\d+$/', '', $this->number);
    }

    /**
     * Open quotations whose validity ends within the "quotation expiring" notice days (the
     * "expiring" filter and figure).
     *
     * @param  Builder<Quotation>  $query
     * @return Builder<Quotation>
     */
    public static function constrainExpiring(Builder $query): Builder
    {
        $days = app(NotificationSettings::class)->rule(NotificationType::QuotationExpiring)['days'] ?? 3;

        return $query->whereIn($query->qualifyColumn('status'), [QuotationStatus::Draft->value, QuotationStatus::Sent->value])
            ->whereNull($query->qualifyColumn('superseded_at'))
            ->whereBetween($query->qualifyColumn('valid_until'), [today(), today()->addDays($days)]);
    }
}
