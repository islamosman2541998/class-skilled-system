<?php

namespace App\Services\Finance;

use App\Services\QuotationCalculator;

/**
 * Invoice totals on the server (spec §5.9, §8.10), half up to the currency decimals.
 */
class InvoiceCalculator
{
    private const SCALE = 8;

    /**
     * Totals from item lines; VAT on the subtotal.
     *
     * @param  list<array{quantity: string|int|float, unit_price: string|int|float}>  $lines
     */
    public static function fromLines(array $lines, string $vatRate, int $decimals = 2): InvoiceTotals
    {
        $totals = app(QuotationCalculator::class)->calculate($lines, null, '0', $vatRate, $decimals);

        return new InvoiceTotals($totals->lineTotals, $totals->subtotal, $totals->vat, $totals->total);
    }

    /**
     * Splits an amount that includes VAT (an installment) so subtotal + VAT equals it exactly.
     */
    public static function fromGross(string $total, string $vatRate, int $decimals = 2): InvoiceTotals
    {
        $total = QuotationCalculator::round($total, $decimals);
        $divisor = bcadd('100', $vatRate, self::SCALE);
        $subtotal = QuotationCalculator::round(bcdiv(bcmul($total, '100', self::SCALE), $divisor, self::SCALE), $decimals);

        return new InvoiceTotals([$subtotal], $subtotal, bcsub($total, $subtotal, $decimals), $total);
    }
}
