<?php

namespace App\Tables\Filters;

use Illuminate\Database\Eloquent\Builder;

class MultiSelectFilter extends SelectFilter
{
    public function type(): string
    {
        return 'multi';
    }

    public function isBlank(mixed $value): bool
    {
        return ! is_array($value) || array_filter($value, fn ($item) => is_scalar($item) && $item !== '') === [];
    }

    public function describe(mixed $value): string
    {
        $options = $this->resolveOptions();

        return collect((array) $value)->map(fn ($item) => $options[$item] ?? $item)->implode('، ');
    }

    protected function constrain(Builder $query, mixed $value): void
    {
        $query->whereIn($this->qualified($query), array_values(array_filter((array) $value, 'is_scalar')));
    }
}
