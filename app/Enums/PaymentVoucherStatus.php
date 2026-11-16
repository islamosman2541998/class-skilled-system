<?php

namespace App\Enums;

/** A payment voucher is active or cancelled with a reason, never deleted (spec §5.2, §8.16). */
enum PaymentVoucherStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('resources.vouchers.statuses.'.$this->value);
    }

    public function color(): string
    {
        return $this === self::Active ? 'success' : 'danger';
    }
}
