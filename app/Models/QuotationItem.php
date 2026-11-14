<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quotation_id
 * @property string|null $section_ar
 * @property string|null $section_en
 * @property int|null $price_item_id
 * @property string $description_ar
 * @property string|null $description_en
 * @property int|null $unit_id
 * @property string $quantity
 * @property string $unit_price
 * @property string $line_total
 * @property int $sort_order
 * @property-read Lookup|null $unit
 */
class QuotationItem extends Model
{
    protected $fillable = [
        'section_ar', 'section_en', 'price_item_id', 'description_ar', 'description_en', 'unit_id',
        'quantity', 'unit_price', 'line_total', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Lookup, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'unit_id');
    }

    public function description(string $language): string
    {
        return $language === 'en' && filled($this->description_en) ? (string) $this->description_en : $this->description_ar;
    }

    public function section(string $language): ?string
    {
        return $language === 'en' && filled($this->section_en) ? $this->section_en : $this->section_ar;
    }
}
