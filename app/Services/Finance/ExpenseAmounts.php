<?php

namespace App\Services\Finance;

use App\Services\QuotationCalculator;
use InvalidArgumentException;

/**
 * Amount before VAT, VAT and total of an expense from what the user typed (spec §8.12):
 * an amount without VAT, an amount that includes VAT, or an expense with no VAT.
 */
final class ExpenseAmounts
{
    public const MODES = ['excluded', 'included', 'none'];

    /**
     * @return array{amount: string, vat_rate: string, vat_amount: string, total: string}
     */
    public static function compute(string $typed, string $mode, string $vatRate, int $decimals = 2): array
    {
        return match ($mode) {
            'none' => ['amount' => QuotationCalculator::round($typed, $decimals), 'vat_rate' => '0.00', 'vat_amount' => bcadd('0', '0', $decimals), 'total' => QuotationCalculator::round($typed, $decimals)],
            'included' => self::fromGross($typed, $vatRate, $decimals),
            'excluded' => self::fromNet($typed, $vatRate, $decimals),
            default => throw new InvalidArgumentException('Unknown VAT mode: '.$mode),
        };
    }

    /**
     * @return array{amount: string, vat_rate: string, vat_amount: string, total: string}
     */
    private static function fromGross(string $typed, string $vatRate, int $decimals): array
    {
        $totals = InvoiceCalculator::fromGross($typed, $vatRate, $decimals);

        return ['amount' => $totals->subtotal, 'vat_rate' => bcadd($vatRate, '0', 2), 'vat_amount' => $totals->vat, 'total' => $totals->total];
    }

    /**
     * @return array{amount: string, vat_rate: string, vat_amount: string, total: string}
     */
    private static function fromNet(string $typed, string $vatRate, int $decimals): array
    {
        $amount = QuotationCalculator::round($typed, $decimals);
        $vat = QuotationCalculator::round(bcdiv(bcmul($amount, $vatRate, 8), '100', 8), $decimals);

        return ['amount' => $amount, 'vat_rate' => bcadd($vatRate, '0', 2), 'vat_amount' => $vat, 'total' => bcadd($amount, $vat, $decimals)];
    }
}
