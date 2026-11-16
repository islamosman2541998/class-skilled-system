<?php

namespace App\Actions\Resources;

use App\Enums\PaymentVoucherStatus;
use App\Models\PaymentVoucher;
use App\Models\Purchase;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Resources\PurchaseBalance;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a payment voucher with a reason (spec §5.2, §8.16): its allocations stop counting, so
 * the purchase invoices go back to what the other vouchers pay. A second call changes nothing.
 */
class CancelPaymentVoucher
{
    public function handle(PaymentVoucher $voucher, string $reason, User $by): void
    {
        DB::transaction(function () use ($voucher, $reason, $by) {
            $locked = PaymentVoucher::query()->with('allocations')->lockForUpdate()->findOrFail($voucher->id);

            if (! $locked->isActive()) {
                return;
            }

            $locked->status = PaymentVoucherStatus::Cancelled;
            $locked->cancel_reason = $reason;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $by->id;
            $locked->save();

            ActivityLogger::record('status_changed', $locked, ['old' => ['status' => PaymentVoucherStatus::Active->value], 'attributes' => ['status' => PaymentVoucherStatus::Cancelled->value, 'cancel_reason' => $reason]], 'payment_vouchers');

            foreach (Purchase::withTrashed()->whereIn('id', $locked->allocations->pluck('purchase_id'))->orderBy('id')->lockForUpdate()->get() as $purchase) {
                PurchaseBalance::refresh($purchase);
            }
        });
    }
}
