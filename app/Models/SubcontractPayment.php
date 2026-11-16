<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A payment line of a subcontract (spec §8.16), billed once as a purchase invoice.
 *
 * @property int $id
 * @property int $subcontract_id
 * @property string $label_ar
 * @property string|null $label_en
 * @property string $amount
 * @property Carbon|null $due_date
 * @property int $sort_order
 * @property-read Subcontract $subcontract
 * @property-read Purchase|null $purchase
 */
class SubcontractPayment extends Model
{
    protected $fillable = ['label_ar', 'label_en', 'amount', 'due_date', 'sort_order'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    /** @return BelongsTo<Subcontract, $this> */
    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(Subcontract::class);
    }

    /** @return HasOne<Purchase, $this> */
    public function purchase(): HasOne
    {
        return $this->hasOne(Purchase::class);
    }

    public function label(): string
    {
        return app()->getLocale() === 'en' && filled($this->label_en) ? (string) $this->label_en : $this->label_ar;
    }
}
