<?php

namespace App\Enums;

enum VariationStatus: string
{
    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('projects.variations.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Proposed => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
