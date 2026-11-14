<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Support\QuotationTotals;
use InvalidArgumentException;

/**
 * Server-side totals of a quotation (spec §5.9, §8.5): line totals, discount, VAT and total, in
 * exact decimal arithmetic, each stored value rounded half up to the currency decimals.
 */
class QuotationCalculator
{
    private const SCALE = 8;

    /**
     * @param  list<array{quantity: string|int|float, unit_price: string|int|float}>  $lines
     */
    public function calculate(array $lines, ?DiscountType $discountType, string $discount, string $vatRate, int $decimals): QuotationTotals
    {
        $lineTotals = [];
        $subtotal = '0';

        foreach ($lines as $line) {
            $quantity = self::number($line['quantity']);
            $price = self::number($line['unit_price']);

            if (bccomp($quantity, '0', self::SCALE) < 0 || bccomp($price, '0', self::SCALE) < 0) {
                throw new InvalidArgumentException('Quantity and price must not be negative.');
            }

            $lineTotal = self::round(bcmul($quantity, $price, self::SCALE), $decimals);
            $lineTotals[] = $lineTotal;
            $subtotal = bcadd($subtotal, $lineTotal, $decimals);
        }

        $discount = self::number($discount);

        if (bccomp($discount, '0', self::SCALE) < 0) {
            throw new InvalidArgumentException('The discount must not be negative.');
        }

        $discountAmount = match ($discountType) {
            DiscountType::Percent => bccomp($discount, '100', self::SCALE) > 0
                ? throw new InvalidArgumentException('A percent discount cannot exceed 100.')
                : self::round(bcdiv(bcmul($subtotal, $discount, self::SCALE), '100', self::SCALE), $decimals),
            DiscountType::Amount => bccomp($discount, $subtotal, self::SCALE) > 0
                ? throw new InvalidArgumentException('The discount cannot exceed the subtotal.')
                : self::round($discount, $decimals),
            null => self::round('0', $decimals),
        };

        $taxable = bcsub($subtotal, $discountAmount, $decimals);
        $vat = self::round(bcdiv(bcmul($taxable, self::number($vatRate), self::SCALE), '100', self::SCALE), $decimals);

        return new QuotationTotals(
            lineTotals: $lineTotals,
            subtotal: self::round($subtotal, $decimals),
            discountAmount: $discountAmount,
            taxable: $taxable,
            vat: $vat,
            total: bcadd($taxable, $vat, $decimals),
            decimals: $decimals,
        );
    }

    /** Rounds a non-negative decimal string half up. */
    public static function round(string $value, int $decimals): string
    {
        $half = '0.'.str_repeat('0', $decimals).'5';

        return bcadd($value, $decimals === 0 ? '0.5' : $half, $decimals);
    }

    private static function number(string|int|float $value): string
    {
        $value = trim(str_replace(',', '', (string) $value));

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Not a number: '.$value);
        }

        // bcmath needs plain notation: only scientific notation goes through a float.
        return stripos($value, 'e') === false ? ltrim($value, '+') : number_format((float) $value, self::SCALE, '.', '');
    }
}
