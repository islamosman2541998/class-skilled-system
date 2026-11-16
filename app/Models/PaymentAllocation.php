<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The part of a payment voucher that pays one purchase invoice (spec §8.16).
 *
 * @property int $id
 * @property int $payment_voucher_id
 * @property int $purchase_id
 * @property string $amount
 * @property-read PaymentVoucher $voucher
 * @property-read Purchase $purchase
 */
class PaymentAllocation extends Model
{
    protected $fillable = ['purchase_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<PaymentVoucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class, 'payment_voucher_id');
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class)->withTrashed();
    }
}
