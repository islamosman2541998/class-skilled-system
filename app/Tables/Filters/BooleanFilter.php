<?php

namespace App\Tables\Filters;

use Illuminate\Database\Eloquent\Builder;

class BooleanFilter extends Filter
{
    public function type(): string
    {
        return 'boolean';
    }

    public function isBlank(mixed $value): bool
    {
        return ! in_array((string) (is_scalar($value) ? $value : ''), ['0', '1'], true);
    }

    public function describe(mixed $value): string
    {
        return (string) $value === '1' ? __('table.yes') : __('table.no');
    }

    protected function constrain(Builder $query, mixed $value): void
    {
        $query->where($this->qualified($query), (string) $value === '1');
    }
}
