<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return __('settings.backups.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Success => 'success',
            self::Failed => 'danger',
        };
    }
}
