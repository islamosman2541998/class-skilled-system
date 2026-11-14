<?php

namespace App\Services\Finance;

final readonly class InvoiceTotals
{
    /**
     * @param  list<string>  $lineTotals
     */
    public function __construct(
        public array $lineTotals,
        public string $subtotal,
        public string $vat,
        public string $total,
    ) {}
}
