<?php

namespace App\Enums;

enum MaintenanceStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('maintenance.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Cancelled => 'neutral',
        };
    }
}
