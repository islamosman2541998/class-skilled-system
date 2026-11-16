<?php

namespace App\Services\Resources;

use App\Actions\Resources\SavePurchase;
use App\Models\PaymentAllocation;
use App\Models\Purchase;

/**
 * Keeps the paid amount and payment status of a purchase invoice from the active voucher
 * allocations (spec §8.16). Callers hold the purchase row lock.
 */
class PurchaseBalance
{
    public static function refresh(Purchase $purchase): void
    {
        $paid = bcadd((string) PaymentAllocation::query()
            ->where('purchase_id', $purchase->id)
            ->whereHas('voucher', fn ($vouchers) => $vouchers->active())
            ->sum('amount'), '0', 2);

        $purchase->paid_amount = $paid;
        $purchase->status = SavePurchase::statusFor($paid, (string) $purchase->total);
        $purchase->save();
    }
}
