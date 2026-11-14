<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('finance.invoices.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Issued => 'info',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** Issued and not cancelled: counts as invoiced and can receive payments. */
    public function isActive(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Paid], true);
    }

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::Issued->value, self::PartiallyPaid->value, self::Paid->value];
    }
}
