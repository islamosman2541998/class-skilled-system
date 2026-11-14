<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Enums\LookupType;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasRecordScope;
use App\Models\Concerns\LogsModelActivity;
use App\Services\ActivityLogger;
use App\Support\Phone;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A request from a prospective client (spec §8.2).
 *
 * @property int $id
 * @property string $number
 * @property int|null $client_id
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_phone_normalized
 * @property string|null $contact_email
 * @property int|null $service_id
 * @property string|null $facility_type
 * @property string|null $project_state
 * @property int|null $emirate_id
 * @property int|null $area_id
 * @property string|null $area_text
 * @property string|null $area_m2
 * @property string|null $expected_start
 * @property string|null $description
 * @property int|null $source_id
 * @property int $status_id
 * @property int|null $assigned_to
 * @property Carbon|null $follow_up_at
 * @property int|null $rejection_reason_id
 * @property string|null $rejection_note
 * @property string|null $external_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Client|null $client
 * @property-read Lookup $status
 */
class Lead extends Model implements GuardsDeletion, HasMedia
{
    /** @use HasFactory<LeadFactory> */
    use HasAttachments, HasFactory, HasRecordScope, LogsModelActivity, SoftDeletes;

    public const CLOSED_CODES = ['won', 'lost'];

    /** @var list<string> Relations that keep a lead from being deleted; later modules add theirs. */
    public static array $linkedRelations = [];

    protected $fillable = [
        'client_id', 'contact_name', 'contact_phone', 'contact_email', 'service_id', 'facility_type',
        'project_state', 'emirate_id', 'area_id', 'area_text', 'area_m2', 'expected_start', 'description',
        'source_id', 'status_id', 'assigned_to', 'follow_up_at', 'rejection_reason_id', 'rejection_note',
        'external_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_at' => 'datetime',
            'area_m2' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            $lead->contact_phone_normalized = Phone::normalize($lead->contact_phone);
        });
    }

    protected static function assigneeColumn(): string
    {
        return 'assigned_to';
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'status_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'service_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'source_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function emirate(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'emirate_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'area_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'rejection_reason_id');
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

    /** The code of the status lookup: new, contacted, visit, quoted, won, lost (custom statuses have none). */
    public function statusCode(): ?string
    {
        return $this->status->code;
    }

    public function isClosed(): bool
    {
        return in_array($this->statusCode(), self::CLOSED_CODES, true);
    }

    /** Name shown in lists: the linked client, else the contact name. */
    public function displayName(): string
    {
        return $this->client->name ?? (string) $this->contact_name;
    }

    public static function statusIdFor(string $code): int
    {
        return (int) Lookup::query()->ofType(LookupType::LeadStatus)->where('code', $code)->value('id');
    }

    /**
     * Moves the lead along the pipeline (visit, quoted, won …) and logs it. A lost lead stays lost.
     */
    public function setStatusCode(string $code, ?User $by = null): void
    {
        $old = $this->statusCode();

        if ($old === $code || $old === 'lost') {
            return;
        }

        $this->status_id = self::statusIdFor($code);
        $this->save();
        $this->load('status');

        ActivityLogger::record('status_changed', $this, ['old' => ['status' => $old], 'attributes' => ['status' => $code]], 'leads');
    }

    /** Brings a lost lead back to "contacted" and clears the rejection. */
    public function reopen(User $by): void
    {
        if ($this->statusCode() !== 'lost') {
            return;
        }

        $this->forceFill(['status_id' => self::statusIdFor('contacted'), 'rejection_reason_id' => null, 'rejection_note' => null])->save();
        $this->load('status');

        ActivityLogger::record('status_changed', $this, ['old' => ['status' => 'lost'], 'attributes' => ['status' => 'contacted']], 'leads');
    }

    /** @return HasMany<SiteVisit, $this> */
    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    /** @return HasMany<Quotation, $this> */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /** @return HasMany<LeadFollowUp, $this> */
    public function followUps(): HasMany
    {
        return $this->hasMany(LeadFollowUp::class)->latest('happened_at')->latest('id');
    }

    public function deletionBlockedReason(User $by): ?string
    {
        foreach (self::$linkedRelations as $relation) {
            if ($this->{$relation}()->exists()) {
                return __('leads.has_records');
            }
        }

        return null;
    }
}
