<?php

namespace App\Enums;

/** Progress claim status (spec §8.14): draft → submitted → approved → invoiced. */
enum ClaimStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return __('site.claims.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'info',
            self::Approved => 'warning',
            self::Invoiced => 'success',
        };
    }

    /** Draft or submitted: one per project at a time. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Submitted], true);
    }

    /** @return list<string> Approved or invoiced: they set the previous quantities and recovered advance. */
    public static function countedValues(): array
    {
        return [self::Approved->value, self::Invoiced->value];
    }
}
