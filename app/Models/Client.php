<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Enums\ClientType;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Concerns\LogsModelActivity;
use App\Support\Phone;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property int $id
 * @property ClientType $type
 * @property string $name_ar
 * @property string|null $name_en
 * @property-read string $name
 * @property string $phone
 * @property string|null $phone_normalized
 * @property string|null $phone2
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $trn
 * @property int|null $emirate_id
 * @property int|null $area_id
 * @property string|null $address
 * @property int|null $source_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Client extends Model implements GuardsDeletion, HasMedia
{
    /** @use HasFactory<ClientFactory> */
    use HasAttachments, HasFactory, HasLocalizedName, LogsModelActivity, SoftDeletes;

    /**
     * Relations that keep a client from being deleted (spec §5.2). Later modules add theirs.
     *
     * @var list<string>
     */
    public static array $linkedRelations = [];

    /**
     * Extra visibility rules for users who only see records assigned to them. Each closure adds
     * an orWhere… constraint (for example: clients that have a lead assigned to the user).
     *
     * @var list<\Closure(Builder<Client>, User): void>
     */
    public static array $visibilityRules = [];

    protected $fillable = [
        'type', 'name_ar', 'name_en', 'phone', 'phone2', 'whatsapp', 'email', 'trn',
        'emirate_id', 'area_id', 'address', 'source_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['type' => ClientType::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Client $client) {
            $client->phone_normalized = Phone::normalize($client->phone);
        });
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** @return HasMany<SiteVisit, $this> */
    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    /** @return HasMany<ClientContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
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
    public function source(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'source_id');
    }

    /**
     * Users of an "all records" role see every client; others see clients linked to their own work.
     *
     * @param  Builder<Client>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->seesAllRecords()) {
            return;
        }

        $query->where(function (Builder $inner) use ($user) {
            $inner->whereRaw('1 = 0');

            foreach (self::$visibilityRules as $rule) {
                $rule($inner, $user);
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->seesAllRecords() || self::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /**
     * Clients whose phone matches, ignoring how it was written.
     *
     * @return Builder<Client>
     */
    public static function withSamePhone(?string $phone, ?int $ignoreId = null): Builder
    {
        return self::query()
            ->where('phone_normalized', Phone::normalize($phone) ?? '-')
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId));
    }

    public function deletionBlockedReason(User $by): ?string
    {
        foreach (self::$linkedRelations as $relation) {
            if ($this->{$relation}()->exists()) {
                return __('clients.has_records');
            }
        }

        return null;
    }
}
