<?php

namespace App\Services\Site;

use App\Services\Finance\InvoiceCalculator;
use App\Services\QuotationCalculator;

/**
 * Progress claim money on the server (spec §5.9, §8.14), each step rounded half up:
 * work → discount → gross → retention → advance recovery → net → VAT. VAT and the total come
 * from InvoiceCalculator on one net line, so the claim total equals its invoice total.
 */
final class ClaimCalculator
{
    private const SCALE = 10;

    /**
     * @param  list<array{contract_quantity: string, unit_price: string, previous_quantity: string, current_quantity: string}>  $lines
     */
    public static function compute(
        array $lines,
        string $discountPercent,
        string $retentionPercent,
        string $advanceAmount,
        string $advanceRecoveredBefore,
        string $contractNet,
        string $vatRate,
        int $decimals = 2,
    ): ClaimTotals {
        $amounts = [];
        $previous = $current = $cumulative = '0';
        $final = $lines !== [];

        foreach ($lines as $line) {
            $price = (string) $line['unit_price'];
            $cumulativeQuantity = bcadd((string) $line['previous_quantity'], (string) $line['current_quantity'], 3);
            $row = [
                'current' => self::round(bcmul((string) $line['current_quantity'], $price, self::SCALE), $decimals),
                'cumulative' => self::round(bcmul($cumulativeQuantity, $price, self::SCALE), $decimals),
            ];
            $amounts[] = $row;
            $previous = bcadd($previous, self::round(bcmul((string) $line['previous_quantity'], $price, self::SCALE), $decimals), $decimals);
            $current = bcadd($current, $row['current'], $decimals);
            $cumulative = bcadd($cumulative, $row['cumulative'], $decimals);
            $final = $final && bccomp($cumulativeQuantity, bcadd((string) $line['contract_quantity'], '0', 3), 3) === 0;
        }

        $discount = self::round(bcdiv(bcmul($current, $discountPercent, self::SCALE), '100', self::SCALE), $decimals);
        $gross = bcsub($current, $discount, $decimals);
        $retention = self::round(bcdiv(bcmul($gross, $retentionPercent, self::SCALE), '100', self::SCALE), $decimals);

        $left = bcsub($advanceAmount, $advanceRecoveredBefore, $decimals);
        $left = bccomp($left, '0', $decimals) < 0 ? bcadd('0', '0', $decimals) : $left;

        if ($final) {
            $recovery = $left;
        } elseif (bccomp($contractNet, '0', $decimals) > 0 && bccomp($gross, '0', $decimals) > 0) {
            $share = self::round(bcdiv(bcmul($gross, $advanceAmount, self::SCALE), $contractNet, self::SCALE), $decimals);
            $recovery = bccomp($share, $left, $decimals) > 0 ? $left : $share;
        } else {
            $recovery = bcadd('0', '0', $decimals);
        }

        // Recovery never takes the claim below zero.
        $afterRetention = bcsub($gross, $retention, $decimals);
        $ceiling = bccomp($afterRetention, '0', $decimals) > 0 ? $afterRetention : bcadd('0', '0', $decimals);
        $recovery = bccomp($recovery, $ceiling, $decimals) > 0 ? $ceiling : $recovery;

        $net = bcsub($afterRetention, $recovery, $decimals);
        $invoice = InvoiceCalculator::fromLines([['quantity' => '1', 'unit_price' => $net]], $vatRate, $decimals);

        return new ClaimTotals($amounts, $previous, $current, $cumulative, $discount, $gross, $retention, $recovery,
            $invoice->subtotal, $invoice->vat, $invoice->total, $final);
    }

    /** Half up away from zero, so variation lines with negative quantities round like their positive twins. */
    public static function round(string $value, int $decimals): string
    {
        return str_starts_with($value, '-')
            ? bcsub('0', QuotationCalculator::round(substr($value, 1), $decimals), $decimals)
            : QuotationCalculator::round($value, $decimals);
    }
}
