<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contract item of a project, copied from the accepted quotation (spec §8.6) and used by
 * variations and progress claims.
 *
 * @property int $id
 * @property int $project_id
 * @property string|null $section_ar
 * @property string|null $section_en
 * @property string $description_ar
 * @property string|null $description_en
 * @property int|null $unit_id
 * @property string $quantity
 * @property string $unit_price
 * @property string $line_total
 * @property int $sort_order
 * @property-read Lookup|null $unit
 */
class ProjectItem extends Model
{
    protected $fillable = ['section_ar', 'section_en', 'description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price', 'line_total', 'sort_order'];

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
}
