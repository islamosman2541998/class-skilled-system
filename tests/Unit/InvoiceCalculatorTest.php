<?php

use App\Services\Finance\InvoiceCalculator;

it('splits a gross amount so subtotal plus vat equals it', function (string $gross, string $subtotal, string $vat) {
    $totals = InvoiceCalculator::fromGross($gross, '5');

    expect($totals->subtotal)->toBe($subtotal)
        ->and($totals->vat)->toBe($vat)
        ->and($totals->total)->toBe($gross)
        ->and(bcadd($totals->subtotal, $totals->vat, 2))->toBe($gross);
})->with([
    ['10000.01', '9523.82', '476.19'],
    ['0.01', '0.01', '0.00'],
    ['105.00', '100.00', '5.00'],
    ['33333.33', '31746.03', '1587.30'],
]);

it('totals lines with half-up rounding', function () {
    $totals = InvoiceCalculator::fromLines([
        ['quantity' => '3', 'unit_price' => '10.005'],
        ['quantity' => '1.5', 'unit_price' => '20'],
    ], '5');

    expect($totals->lineTotals)->toBe(['30.02', '30.00'])
        ->and($totals->subtotal)->toBe('60.02')
        ->and($totals->vat)->toBe('3.00')
        ->and($totals->total)->toBe('63.02');
});
