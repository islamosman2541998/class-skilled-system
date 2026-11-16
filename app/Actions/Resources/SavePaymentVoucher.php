<?php

namespace App\Actions\Resources;

use App\Enums\DocumentType;
use App\Enums\PaymentVoucherStatus;
use App\Models\PaymentVoucher;
use App\Models\User;
use App\Services\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a payment voucher (spec §8.16). The supplier is fixed once created; the
 * amount can change but never below what is already allocated.
 */
class SavePaymentVoucher
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data  validated SavePaymentVoucherRequest data
     */
    public function handle(array $data, User $by, ?PaymentVoucher $voucher = null): PaymentVoucher
    {
        return DB::transaction(function () use ($data, $by, $voucher) {
            $creating = $voucher === null;
            $voucher = $creating ? new PaymentVoucher : PaymentVoucher::query()->lockForUpdate()->findOrFail($voucher->id);

            if (! $creating && ! $voucher->isActive()) {
                throw ValidationException::withMessages(['amount' => __('resources.vouchers.cancelled_locked')]);
            }

            $voucher->fill([
                'voucher_date' => $data['voucher_date'],
                'amount' => (string) $data['amount'],
                'payment_method_id' => $data['payment_method_id'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($creating) {
                $voucher->supplier_id = (int) $data['supplier_id'];
                $voucher->number = $this->numbers->next(DocumentType::PaymentVoucher, $voucher->voucher_date);
                $voucher->status = PaymentVoucherStatus::Active;
                $voucher->created_by = $by->id;
            } elseif (bccomp((string) $voucher->amount, $voucher->allocated(), 2) < 0) {
                throw ValidationException::withMessages(['amount' => __('resources.vouchers.amount_below_allocated', ['amount' => money($voucher->allocated())])]);
            }

            $voucher->save();

            return $voucher;
        });
    }
}
