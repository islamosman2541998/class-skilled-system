<?php

namespace App\Enums;

/** Installment states (spec §8.8). Invoiced and collected are set by Stage 3. */
enum InstallmentStatus: string
{
    case NotDue = 'not_due';
    case Due = 'due';
    case Invoiced = 'invoiced';
    case Collected = 'collected';

    public function label(): string
    {
        return __('projects.installments.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::NotDue => 'neutral',
            self::Due => 'warning',
            self::Invoiced => 'info',
            self::Collected => 'success',
        };
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::Invoiced, self::Collected], true);
    }
}
