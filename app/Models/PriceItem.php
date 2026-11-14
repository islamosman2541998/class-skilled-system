<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PriceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A ready-made line for quotations (spec §8.4).
 *
 * @property int $id
 * @property int|null $service_id
 * @property string $description_ar
 * @property string|null $description_en
 * @property-read string $description
 * @property int $unit_id
 * @property string $default_price
 * @property bool $is_active
 * @property-read Lookup $unit
 * @property-read Lookup|null $service
 */
class PriceItem extends Model
{
    /** @use HasFactory<PriceItemFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    protected $fillable = ['service_id', 'description_ar', 'description_en', 'unit_id', 'default_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getDescriptionAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->description_en) ? (string) $this->description_en : $this->description_ar;
    }

    /** @return BelongsTo<Lookup, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'unit_id');
    }

    /** @return BelongsTo<Lookup, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'service_id');
    }
}
