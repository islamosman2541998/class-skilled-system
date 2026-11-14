<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The part of a receipt paid against one invoice (spec §8.11).
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $invoice_id
 * @property string $amount
 * @property-read Receipt $receipt
 * @property-read Invoice $invoice
 */
class ReceiptAllocation extends Model
{
    protected $fillable = ['invoice_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }
}
