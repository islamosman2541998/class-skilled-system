<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Enums\SupplierType;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A supplier or subcontractor (spec §8.15). Not scoped by project: every user with
 * suppliers.view sees them; their purchases follow project visibility.
 *
 * @property int $id
 * @property SupplierType $type
 * @property string $name_ar
 * @property string|null $name_en
 * @property-read string $name
 * @property int|null $specialty_id
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $trn
 * @property string|null $address
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_iban
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Lookup|null $specialty
 */
class Supplier extends Model implements GuardsDeletion, HasMedia
{
    /** @use HasFactory<SupplierFactory> */
    use HasAttachments, HasFactory, LogsModelActivity, SoftDeletes;

    /** @var list<string> Relations that keep a supplier from being deleted; later tasks add theirs. */
    public static array $linkedRelations = [];

    protected $fillable = [
        'type', 'name_ar', 'name_en', 'specialty_id', 'phone', 'email', 'trn', 'address',
        'bank_name', 'bank_account_name', 'bank_iban', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => SupplierType::class,
            'is_active' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en) ? (string) $this->name_en : $this->name_ar;
    }

    /** @return BelongsTo<Lookup, $this> */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'specialty_id');
    }

    /** @return HasMany<Purchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** @return HasMany<Subcontract, $this> */
    public function subcontracts(): HasMany
    {
        return $this->hasMany(Subcontract::class);
    }

    public function deletionBlockedReason(User $by): ?string
    {
        foreach (self::$linkedRelations as $relation) {
            if ($this->{$relation}()->exists()) {
                return __('resources.suppliers.has_records');
            }
        }

        return null;
    }
}
