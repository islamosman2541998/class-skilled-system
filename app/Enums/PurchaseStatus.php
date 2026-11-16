<?php

namespace App\Enums;

/** Payment status of a purchase invoice, from the active payment voucher allocations (spec §8.16). */
enum PurchaseStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return __('resources.purchases.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::Partial => 'warning',
            self::Paid => 'success',
        };
    }
}
