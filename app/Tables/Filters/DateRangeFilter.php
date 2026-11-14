<?php

namespace App\Tables\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A second date range besides the table's main one (for example the due date of invoices).
 * Value shape: ['from' => ?Y-m-d, 'to' => ?Y-m-d]. Both ends are inclusive.
 */
class DateRangeFilter extends Filter
{
    public function type(): string
    {
        return 'dates';
    }

    public function isBlank(mixed $value): bool
    {
        return $this->bound($value, 'from') === null && $this->bound($value, 'to') === null;
    }

    public function describe(mixed $value): string
    {
        $from = $this->bound($value, 'from');
        $to = $this->bound($value, 'to');

        return match (true) {
            $from !== null && $to !== null => self::show($from).' - '.self::show($to),
            $from !== null => '≥ '.self::show($from),
            default => '≤ '.self::show((string) $to),
        };
    }

    protected function constrain(Builder $query, mixed $value): void
    {
        $column = $this->qualified($query);

        if (($from = $this->bound($value, 'from')) !== null) {
            $query->whereDate($column, '>=', $from);
        }

        if (($to = $this->bound($value, 'to')) !== null) {
            $query->whereDate($column, '<=', $to);
        }
    }

    private static function show(string $date): string
    {
        return fmt_date(Carbon::parse($date));
    }

    private function bound(mixed $value, string $key): ?string
    {
        $bound = is_array($value) ? ($value[$key] ?? null) : null;

        return is_string($bound) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bound) === 1 ? $bound : null;
    }
}
