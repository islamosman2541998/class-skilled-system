<?php

namespace App\Services\Site;

/** Money of a progress claim, computed by ClaimCalculator (spec §5.9, §8.14). */
final class ClaimTotals
{
    /**
     * @param  list<array{current: string, cumulative: string}>  $lineAmounts
     */
    public function __construct(
        public readonly array $lineAmounts,
        public readonly string $workPrevious,
        public readonly string $workCurrent,
        public readonly string $workCumulative,
        public readonly string $discount,
        public readonly string $gross,
        public readonly string $retention,
        public readonly string $advanceRecovery,
        public readonly string $net,
        public readonly string $vat,
        public readonly string $total,
        public readonly bool $final,
    ) {}
}
