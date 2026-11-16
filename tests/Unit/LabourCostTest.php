<?php

use App\Enums\AttendanceStatus;
use App\Services\Resources\LabourCost;

it('present costs the daily wage', function () {
    expect(LabourCost::compute('200', AttendanceStatus::Present, '0', 8, '1.25'))->toBe('200.00');
});

it('half day costs half', function () {
    expect(LabourCost::compute('175', AttendanceStatus::Half, '0', 8, '1.25'))->toBe('87.50');
});

it('overtime 2 hours at 200 wage, 8 hours, 1.25 costs 62.50', function () {
    expect(LabourCost::compute('200', AttendanceStatus::Present, '2', 8, '1.25'))->toBe('262.50');
});

it('absent with overtime counts only the overtime', function () {
    expect(LabourCost::compute('200', AttendanceStatus::Absent, '2', 8, '1.25'))->toBe('62.50')
        ->and(LabourCost::compute('150', AttendanceStatus::Absent, '2', 8, '1.25'))->toBe('46.88');
});
