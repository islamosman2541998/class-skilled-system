<?php

namespace App\Enums;

enum SiteVisitStatus: string
{
    case Scheduled = 'scheduled';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('visits.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Done => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
