<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('finance.receipts.statuses.'.$this->value);
    }

    public function color(): string
    {
        return $this === self::Active ? 'success' : 'danger';
    }
}
