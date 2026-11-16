<?php

namespace App\Actions\Resources;

use App\Models\PaymentVoucher;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Resources\PurchaseBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the allocations of a payment voucher (spec §8.16). The voucher and every purchase row
 * are locked in id order, so two vouchers paying one invoice at the same moment never pay more
 * than its total.
 */
class AllocatePaymentVoucher
{
    /**
     * @param  array<int|string, mixed>  $amounts  purchase id => amount; blank or zero means none
     *
     * @throws ValidationException on the "allocations" key
     */
    public function handle(PaymentVoucher $voucher, array $amounts, User $by): void
    {
        DB::transaction(function () use ($voucher, $amounts) {
            $locked = PaymentVoucher::query()->with('allocations')->lockForUpdate()->findOrFail($voucher->id);

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['allocations' => __('resources.vouchers.cancelled_locked')]);
            }

            $wanted = [];

            foreach ($amounts as $purchaseId => $amount) {
                if (is_numeric($amount) && bccomp((string) $amount, '0', 2) > 0) {
                    $wanted[(int) $purchaseId] = bcadd((string) $amount, '0', 2);
                }
            }

            $current = $locked->allocations->pluck('amount', 'purchase_id')->map(fn ($amount) => (string) $amount)->all();
            $ids = array_values(array_unique([...array_keys($current), ...array_keys($wanted)]));
            sort($ids);
            $purchases = Purchase::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sum = '0';

            foreach ($wanted as $purchaseId => $amount) {
                $purchase = $purchases->get($purchaseId);

                if ($purchase === null || $purchase->supplier_id !== $locked->supplier_id) {
                    throw ValidationException::withMessages(['allocations' => __('resources.vouchers.invalid_purchase')]);
                }

                // What this voucher already pays on the invoice is given back before the check.
                $open = bcadd($purchase->remaining(), $current[$purchaseId] ?? '0', 2);

                if (bccomp($amount, $open, 2) > 0) {
                    throw ValidationException::withMessages(['allocations' => __('resources.vouchers.exceeds_purchase', ['number' => $purchase->number, 'amount' => money($open)])]);
                }

                $sum = bcadd($sum, $amount, 2);
            }

            if (bccomp($sum, (string) $locked->amount, 2) > 0) {
                throw ValidationException::withMessages(['allocations' => __('resources.vouchers.exceeds_voucher', ['amount' => money($locked->amount)])]);
            }

            $locked->allocations()->delete();

            foreach ($wanted as $purchaseId => $amount) {
                $locked->allocations()->create(['purchase_id' => $purchaseId, 'amount' => $amount]);
            }

            foreach ($purchases as $purchase) {
                PurchaseBalance::refresh($purchase);
            }
        });
    }
}
