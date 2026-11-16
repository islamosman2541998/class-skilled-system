<?php

namespace App\Enums;

/** Status of a subcontract (spec §8.16). */
enum SubcontractStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('resources.subcontracts.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
