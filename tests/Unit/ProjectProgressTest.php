<?php

use App\Services\ProjectProgress;
use Illuminate\Support\Collection;

function stagesOf(array $rows): Collection
{
    return collect($rows)->map(fn (array $row) => (object) ['weight' => $row[0], 'progress' => $row[1]]);
}

it('weighs stage progress and normalises the weights', function () {
    expect(ProjectProgress::compute(stagesOf([['10', '100'], ['50', '40'], ['40', '0']])))->toBe('30.00')
        ->and(ProjectProgress::compute(stagesOf([['1', '100'], ['3', '50']])))->toBe('62.50');
});

it('gives 0.00 without stages or with zero weights', function () {
    expect(ProjectProgress::compute(collect()))->toBe('0.00')
        ->and(ProjectProgress::compute(stagesOf([['0', '100']])))->toBe('0.00');
});
