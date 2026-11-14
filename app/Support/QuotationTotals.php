<?php

namespace App\Support;

use App\Services\QuotationCalculator;

/**
 * Totals of a priced document, every value already rounded to the currency decimals.
 */
final readonly class QuotationTotals
{
    /**
     * @param  list<string>  $lineTotals
     */
    public function __construct(
        public array $lineTotals,
        public string $subtotal,
        public string $discountAmount,
        public string $taxable,
        public string $vat,
        public string $total,
        public int $decimals,
    ) {}

    /**
     * Amounts of a payment schedule given in percentages. Every line is rounded and the last one
     * takes the remainder, so the amounts always add up to the total exactly.
     *
     * @param  list<string>  $percents
     * @return list<string>
     */
    public function paymentAmounts(array $percents): array
    {
        $amounts = [];
        $allocated = '0';
        $last = count($percents) - 1;

        foreach ($percents as $index => $percent) {
            if ($index === $last) {
                $amounts[] = bcsub($this->total, $allocated, $this->decimals);

                break;
            }

            $amount = QuotationCalculator::round(bcdiv(bcmul($this->total, (string) $percent, 8), '100', 8), $this->decimals);
            $allocated = bcadd($allocated, $amount, $this->decimals);
            $amounts[] = $amount;
        }

        return $amounts;
    }
}
