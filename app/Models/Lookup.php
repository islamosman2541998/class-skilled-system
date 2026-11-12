<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Enums\LookupType;
use App\Models\Concerns\HasLocalizedName;
use App\Support\LookupUsage;
use Database\Factories\LookupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * One row of a managed list.
 *
 * @property int $id
 * @property LookupType $type
 * @property int|null $parent_id
 * @property string $name_ar
 * @property string|null $name_en
 * @property string|null $code stable key for rows the code refers to (never edited from the screen)
 * @property string|null $color success|warning|danger|info|neutral
 * @property bool $is_active
 * @property bool $is_system
 * @property int $sort_order
 * @property-read string $name
 * @property-read Lookup|null $parent
 */
class Lookup extends Model implements GuardsDeletion
{
    /** @use HasFactory<LookupFactory> */
    use HasFactory, HasLocalizedName, SoftDeletes;

    public const COLORS = ['success', 'warning', 'danger', 'info', 'neutral'];

    protected $fillable = ['type', 'parent_id', 'name_ar', 'name_en', 'code', 'color', 'is_active', 'is_system', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => LookupType::class,
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn (Lookup $lookup) => Cache::forget(self::cacheKey($lookup->type));

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    /**
     * @return BelongsTo<Lookup, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Lookup, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @param  Builder<Lookup>  $query
     */
    public function scopeOfType(Builder $query, LookupType $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * @param  Builder<Lookup>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Active rows of a list in display order, cached until the list changes.
     *
     * @return Collection<int, Lookup>
     */
    public static function options(LookupType $type, ?int $parentId = null): Collection
    {
        /** @var Collection<int, Lookup> $rows */
        $rows = Cache::rememberForever(
            self::cacheKey($type),
            fn () => self::query()->ofType($type)->active()->orderBy('sort_order')->orderBy('id')->get(),
        );

        return $parentId === null ? $rows : $rows->where('parent_id', $parentId)->values();
    }

    public function deletionBlockedReason(User $by): ?string
    {
        if ($this->is_system) {
            return __('settings.lookup_is_system');
        }

        if ($this->children()->exists()) {
            return __('settings.lookup_has_children');
        }

        return LookupUsage::inUse($this) ? __('settings.lookup_in_use') : null;
    }

    private static function cacheKey(LookupType $type): string
    {
        return 'lookups.'.$type->value;
    }
}
