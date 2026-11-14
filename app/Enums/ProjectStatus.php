<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('projects.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'neutral',
            self::InProgress => 'info',
            self::OnHold => 'warning',
            self::Completed, self::Delivered => 'success',
            self::Cancelled => 'danger',
        };
    }

    /** States in which work is finished or stopped for good, so lateness no longer applies. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Delivered, self::Cancelled], true);
    }
}
