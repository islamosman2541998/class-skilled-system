<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Enums\BillingMethod;
use App\Enums\ProjectStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A project (spec §8.6). Engineers of an "assigned only" role see the projects they manage or
 * belong to.
 *
 * @property int $id
 * @property string $number
 * @property string $title_ar
 * @property string|null $title_en
 * @property-read string $title
 * @property int $client_id
 * @property int|null $quotation_id
 * @property int|null $service_id
 * @property int|null $emirate_id
 * @property string|null $location
 * @property string $original_value
 * @property string $variations_total
 * @property string $contract_value
 * @property string $vat_rate
 * @property BillingMethod $billing_method
 * @property Carbon|null $start_date
 * @property Carbon|null $planned_end_date
 * @property Carbon|null $actual_end_date
 * @property int $manager_id
 * @property ProjectStatus $status
 * @property string $progress
 * @property string|null $description
 * @property string $invoiced_total
 * @property string $collected_total
 * @property string $cost_total
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Client $client
 * @property-read User $manager
 */
class Project extends Model implements GuardsDeletion, HasMedia
{
    /** @use HasFactory<ProjectFactory> */
    use HasAttachments, HasFactory, LogsModelActivity, SoftDeletes;

    /** @var list<string> Relations that keep a project from being deleted; later modules add theirs. */
    public static array $linkedRelations = [];

    protected $fillable = [
        'title_ar', 'title_en', 'client_id', 'service_id', 'emirate_id', 'location', 'original_value',
        'billing_method', 'start_date', 'planned_end_date', 'actual_end_date', 'manager_id', 'status', 'description',
    ];

    protected function casts(): array
    {
        return [
            'billing_method' => BillingMethod::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'planned_end_date' => 'date',
            'actual_end_date' => 'date',
            'original_value' => 'decimal:2',
            'variations_total' => 'decimal:2',
            'contract_value' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'progress' => 'decimal:2',
            'invoiced_total' => 'decimal:2',
            'collected_total' => 'decimal:2',
            'cost_total' => 'decimal:2',
        ];
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

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withTimestamps();
    }

    /** @return HasMany<ProjectStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStage::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ProjectInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(ProjectInstallment::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<ProjectItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllRecords()) {
            return;
        }

        $query->where(fn (Builder $inner) => $inner
            ->where($query->qualifyColumn('manager_id'), $user->id)
            ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id)));
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->seesAllRecords()
            || $this->manager_id === $user->id
            || $this->members()->whereKey($user->id)->exists();
    }

    /** Days until the planned end; negative when late; null without a planned end. */
    public function daysLeft(): ?int
    {
        return $this->planned_end_date ? (int) today()->diffInDays($this->planned_end_date, false) : null;
    }

    public function isLate(): bool
    {
        return $this->planned_end_date !== null && $this->planned_end_date->lt(today()) && ! $this->status->isClosed();
    }

    public function outstanding(): string
    {
        return bcsub((string) $this->invoiced_total, (string) $this->collected_total, 2);
    }

    public function deletionBlockedReason(User $by): ?string
    {
        foreach (self::$linkedRelations as $relation) {
            if ($this->{$relation}()->exists()) {
                return __('projects.has_records');
            }
        }

        return null;
    }
}
