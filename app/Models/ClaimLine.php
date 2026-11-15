<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line of a progress claim: a contract item or an item of an approved variation, with the
 * previous, current and cumulative quantities (spec §8.14).
 *
 * @property int $id
 * @property int $claim_id
 * @property int|null $project_item_id
 * @property int|null $variation_item_id
 * @property string $description_ar
 * @property string|null $description_en
 * @property int|null $unit_id
 * @property string $contract_quantity
 * @property string $unit_price
 * @property string $previous_quantity
 * @property string $current_quantity
 * @property string $cumulative_quantity
 * @property string $current_amount
 * @property string $cumulative_amount
 * @property int $sort_order
 * @property-read Lookup|null $unit
 */
class ClaimLine extends Model
{
    protected $fillable = [
        'project_item_id', 'variation_item_id', 'description_ar', 'description_en', 'unit_id', 'contract_quantity', 'unit_price',
        'previous_quantity', 'current_quantity', 'cumulative_quantity', 'current_amount', 'cumulative_amount', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'contract_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'previous_quantity' => 'decimal:3',
            'current_quantity' => 'decimal:3',
            'cumulative_quantity' => 'decimal:3',
            'current_amount' => 'decimal:2',
            'cumulative_amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Lookup, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'unit_id');
    }

    /** @return BelongsTo<Claim, $this> */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function description(): string
    {
        return app()->getLocale() === 'en' && filled($this->description_en) ? (string) $this->description_en : $this->description_ar;
    }

    /** Cumulative as a percent of the contract quantity, 2 decimals. */
    public function cumulativePercent(): string
    {
        if (bccomp((string) $this->contract_quantity, '0', 3) === 0) {
            return '0.00';
        }

        return bcdiv(bcmul((string) $this->cumulative_quantity, '100', 6), (string) $this->contract_quantity, 2);
    }
}
