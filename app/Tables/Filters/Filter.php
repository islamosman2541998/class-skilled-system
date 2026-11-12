<?php

namespace App\Tables\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * A filter shown above a DataTable. The value lives in DataTable::$filters[$key].
 */
abstract class Filter
{
    protected ?string $column = null;

    protected ?Closure $callback = null;

    final public function __construct(public readonly string $key, public readonly string $labelKey) {}

    public static function make(string $key, string $labelKey): static
    {
        return new static($key, $labelKey);
    }

    /** Database column to constrain (defaults to the filter key). */
    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    /** Closure(Builder $query, mixed $value): replaces the default constraint. */
    public function query(Closure $callback): static
    {
        $this->callback = $callback;

        return $this;
    }

    public function label(): string
    {
        return __($this->labelKey);
    }

    public function apply(Builder $query, mixed $value): void
    {
        if ($this->isBlank($value)) {
            return;
        }

        if ($this->callback !== null) {
            ($this->callback)($query, $value);

            return;
        }

        $this->constrain($query, $value);
    }

    public function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** The form control used in the filter row: select | multi | boolean | range. */
    abstract public function type(): string;

    /** Text of the chip shown while the filter is active. */
    abstract public function describe(mixed $value): string;

    abstract protected function constrain(Builder $query, mixed $value): void;

    protected function qualified(Builder $query): string
    {
        $column = $this->column ?? $this->key;

        return str_contains($column, '.') ? $column : $query->qualifyColumn($column);
    }
}
