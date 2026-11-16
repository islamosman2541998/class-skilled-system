<?php

namespace App\Services\Resources;

use App\Enums\AttendanceStatus;
use App\Services\QuotationCalculator;

/**
 * Cost of one attendance day (spec §8.17): wage × day share + overtime hours × wage ÷ working
 * hours × overtime multiplier, rounded half up. No salaries; this is the project cost of the day.
 */
final class LabourCost
{
    public static function compute(string $wage, AttendanceStatus $status, string $overtimeHours, int $hoursPerDay, string $multiplier): string
    {
        $base = bcmul($wage, $status->factor(), 10);
        $overtime = $hoursPerDay > 0
            ? bcmul(bcdiv(bcmul($overtimeHours, $wage, 10), (string) $hoursPerDay, 10), $multiplier, 10)
            : '0';

        return QuotationCalculator::round(bcadd($base, $overtime, 10), 2);
    }
}
