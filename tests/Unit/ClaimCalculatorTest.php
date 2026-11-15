<?php

use App\Services\Site\ClaimCalculator;

it('computes current, cumulative, discount, retention, recovery, net and VAT', function () {
    $totals = ClaimCalculator::compute([
        ['contract_quantity' => '100', 'unit_price' => '50', 'previous_quantity' => '40', 'current_quantity' => '30'],
        ['contract_quantity' => '1', 'unit_price' => '20000', 'previous_quantity' => '0', 'current_quantity' => '0.5'],
    ], '5', '10', '5000', '0', '23750', '5', 2);

    expect($totals->workPrevious)->toBe('2000.00')
        ->and($totals->workCurrent)->toBe('11500.00')
        ->and($totals->workCumulative)->toBe('13500.00')
        ->and($totals->discount)->toBe('575.00')
        ->and($totals->gross)->toBe('10925.00')
        ->and($totals->retention)->toBe('1092.50')
        ->and($totals->advanceRecovery)->toBe('2300.00')
        ->and($totals->net)->toBe('7532.50')
        ->and($totals->vat)->toBe('376.63')
        ->and($totals->total)->toBe('7909.13')
        ->and($totals->lineAmounts[0])->toBe(['current' => '1500.00', 'cumulative' => '3500.00'])
        ->and($totals->lineAmounts[1])->toBe(['current' => '10000.00', 'cumulative' => '10000.00'])
        ->and($totals->final)->toBeFalse();
});

it('recovery is capped by what is left and the final claim takes the rest', function () {
    $lines = [['contract_quantity' => '100', 'unit_price' => '100', 'previous_quantity' => '60', 'current_quantity' => '30']];

    // 3,000 of gross would recover 1,500 of the 5,000 advance, but only 1,000 is left.
    $capped = ClaimCalculator::compute($lines, '0', '0', '5000', '4000', '10000', '5', 2);
    expect($capped->advanceRecovery)->toBe('1000.00')->and($capped->final)->toBeFalse();

    // The last 10%: proportion gives 500, the final claim takes all 765.44 left.
    $final = ClaimCalculator::compute([['contract_quantity' => '100', 'unit_price' => '100', 'previous_quantity' => '90', 'current_quantity' => '10']],
        '0', '0', '5000', '4234.56', '10000', '5', 2);
    expect($final->final)->toBeTrue()->and($final->advanceRecovery)->toBe('765.44')->and($final->net)->toBe('234.56');

    // Recovery never takes the net below zero.
    $small = ClaimCalculator::compute([['contract_quantity' => '100', 'unit_price' => '100', 'previous_quantity' => '90', 'current_quantity' => '10']],
        '0', '10', '5000', '2000', '10000', '5', 2);
    expect($small->advanceRecovery)->toBe('900.00')->and($small->net)->toBe('0.00');
});

it('net 10,000.01 at 5% gives VAT 500.00 and total 10,500.01', function () {
    $totals = ClaimCalculator::compute([
        ['contract_quantity' => '1', 'unit_price' => '20000.02', 'previous_quantity' => '0', 'current_quantity' => '0.5'],
    ], '0', '0', '0', '0', '20000.02', '5', 2);

    expect($totals->net)->toBe('10000.01')
        ->and($totals->vat)->toBe('500.00')
        ->and($totals->total)->toBe('10500.01');
});

it('a negative variation line lowers the work', function () {
    $totals = ClaimCalculator::compute([
        ['contract_quantity' => '10', 'unit_price' => '100', 'previous_quantity' => '0', 'current_quantity' => '10'],
        ['contract_quantity' => '-2', 'unit_price' => '100.005', 'previous_quantity' => '0', 'current_quantity' => '-1'],
    ], '0', '0', '0', '0', '800', '5', 2);

    expect($totals->lineAmounts[1]['current'])->toBe('-100.01')
        ->and($totals->workCurrent)->toBe('899.99');
});
