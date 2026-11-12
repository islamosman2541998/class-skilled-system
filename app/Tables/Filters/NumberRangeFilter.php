<?php

namespace App\Tables\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Value shape: ['min' => ?numeric, 'max' => ?numeric]. Both ends are inclusive.
 */
class NumberRangeFilter extends Filter
{
    public function type(): string
    {
        return 'range';
    }

    public function isBlank(mixed $value): bool
    {
        return ! is_array($value) || ($this->bound($value, 'min') === null && $this->bound($value, 'max') === null);
    }

    public function describe(mixed $value): string
    {
        $min = $this->bound($value, 'min');
        $max = $this->bound($value, 'max');

        return match (true) {
            $min !== null && $max !== null => "{$min} - {$max}",
            $min !== null => "≥ {$min}",
            default => "≤ {$max}",
        };
    }

    protected function constrain(Builder $query, mixed $value): void
    {
        $column = $this->qualified($query);

        if (($min = $this->bound($value, 'min')) !== null) {
            $query->where($column, '>=', $min);
        }

        if (($max = $this->bound($value, 'max')) !== null) {
            $query->where($column, '<=', $max);
        }
    }

    private function bound(mixed $value, string $key): ?string
    {
        $bound = is_array($value) ? ($value[$key] ?? null) : null;

        return is_numeric($bound) ? (string) $bound : null;
    }
}
