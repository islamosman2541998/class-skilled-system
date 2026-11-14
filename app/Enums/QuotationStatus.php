<?php

namespace App\Enums;

/**
 * Quotation states (spec §8.5). "Expired" is never stored: it is shown for a draft or sent
 * quotation whose validity date has passed.
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return __('quotations.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Expired => 'warning',
        };
    }

    /** States a quotation can be stored with. */
    public static function stored(): array
    {
        return [self::Draft, self::Sent, self::Accepted, self::Rejected];
    }
}
