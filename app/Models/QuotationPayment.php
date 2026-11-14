<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $quotation_id
 * @property string $label_ar
 * @property string|null $label_en
 * @property string $percent
 * @property string $amount
 * @property int $sort_order
 */
class QuotationPayment extends Model
{
    protected $fillable = ['label_ar', 'label_en', 'percent', 'amount', 'sort_order'];

    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function label(string $language): string
    {
        return $language === 'en' && filled($this->label_en) ? (string) $this->label_en : $this->label_ar;
    }
}
