<?php

namespace App\Models;

use App\Enums\EquipmentOwnership;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A piece of equipment, owned or rented, with a daily cost (spec §8.17). Assigned to projects for a
 * period; each assignment stores its cost.
 *
 * @property int $id
 * @property string $name_ar
 * @property string|null $name_en
 * @property-read string $name
 * @property int|null $type_id
 * @property EquipmentOwnership $ownership
 * @property string $daily_cost
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $created_by
 * @property-read Lookup|null $type
 */
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = ['name_ar', 'name_en', 'type_id', 'ownership', 'daily_cost', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'ownership' => EquipmentOwnership::class,
            'daily_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en) ? (string) $this->name_en : $this->name_ar;
    }

    /** @return BelongsTo<Lookup, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'type_id');
    }

    /** @return HasMany<EquipmentAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(EquipmentAssignment::class);
    }
}
