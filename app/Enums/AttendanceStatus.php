<?php

namespace App\Enums;

/** Daily attendance of a worker on a project (spec §8.17). */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Half = 'half';
    case Absent = 'absent';

    public function label(): string
    {
        return __('resources.attendance.statuses.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Half => 'warning',
            self::Absent => 'neutral',
        };
    }

    /** Share of the daily wage the day costs. */
    public function factor(): string
    {
        return match ($this) {
            self::Present => '1',
            self::Half => '0.5',
            self::Absent => '0',
        };
    }
}
