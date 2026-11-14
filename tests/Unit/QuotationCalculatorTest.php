<?php

use App\Enums\DiscountType;
use App\Services\QuotationCalculator;

function calc(): QuotationCalculator
{
    return new QuotationCalculator;
}

it('computes lines, percent discount, VAT and total', function () {
    $t = calc()->calculate([['quantity' => '120.5', 'unit_price' => '45.00'], ['quantity' => '1', 'unit_price' => '3500']], DiscountType::Percent, '10', '5.00', 2);

    expect($t->lineTotals)->toBe(['5422.50', '3500.00'])
        ->and($t->subtotal)->toBe('8922.50')
        ->and($t->discountAmount)->toBe('892.25')
        ->and($t->taxable)->toBe('8030.25')
        ->and($t->vat)->toBe('401.51')
        ->and($t->total)->toBe('8431.76');
});

it('applies an amount discount', function () {
    $t = calc()->calculate([['quantity' => '2', 'unit_price' => '500']], DiscountType::Amount, '100', '5', 2);

    expect($t->discountAmount)->toBe('100.00')->and($t->taxable)->toBe('900.00')->and($t->vat)->toBe('45.00')->and($t->total)->toBe('945.00');
});

it('works without a discount', function () {
    $t = calc()->calculate([['quantity' => '3', 'unit_price' => '33.333']], null, '0', '5', 2);

    expect($t->lineTotals)->toBe(['100.00'])->and($t->discountAmount)->toBe('0.00')->and($t->total)->toBe('105.00');
});

it('rounds to the decimals setting', function () {
    $t = calc()->calculate([['quantity' => '1', 'unit_price' => '10.0005']], null, '0', '0', 3);

    expect($t->lineTotals)->toBe(['10.001'])->and($t->total)->toBe('10.001');
});

it('rounds half up, not half to even', function () {
    $t = calc()->calculate([['quantity' => '1', 'unit_price' => '0.125']], null, '0', '0', 2);

    expect($t->lineTotals)->toBe(['0.13']);
});

it('rejects a percent over 100, a negative quantity and an amount discount over the subtotal', function () {
    expect(fn () => calc()->calculate([['quantity' => '1', 'unit_price' => '10']], DiscountType::Percent, '101', '5', 2))->toThrow(InvalidArgumentException::class)
        ->and(fn () => calc()->calculate([['quantity' => '-1', 'unit_price' => '10']], null, '0', '5', 2))->toThrow(InvalidArgumentException::class)
        ->and(fn () => calc()->calculate([['quantity' => '1', 'unit_price' => '10']], DiscountType::Amount, '10.01', '5', 2))->toThrow(InvalidArgumentException::class);
});

it('splits payments so the amounts add up to the total exactly', function () {
    $t = calc()->calculate([['quantity' => '1', 'unit_price' => '1000.01']], null, '0', '0', 2);

    expect($t->paymentAmounts(['30', '40', '30']))->toBe(['300.00', '400.00', '300.01']);
});
