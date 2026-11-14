<?php

namespace App\Enums;

enum StageStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return __('projects.stage_statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'neutral',
            self::InProgress => 'info',
            self::Done => 'success',
        };
    }
}
