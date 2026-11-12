<?php

namespace App\Tables\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class SelectFilter extends Filter
{
    /** @var array<int|string, string>|Closure */
    protected array|Closure $options = [];

    /**
     * @param  array<int|string, string>|Closure  $options  value => label, or a closure returning that
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<int|string, string>
     */
    public function resolveOptions(): array
    {
        if ($this->options instanceof Closure) {
            $this->options = collect(($this->options)())->all();
        }

        return $this->options;
    }

    public function type(): string
    {
        return 'select';
    }

    public function describe(mixed $value): string
    {
        return (string) ($this->resolveOptions()[$value] ?? $value);
    }

    public function isBlank(mixed $value): bool
    {
        return parent::isBlank($value) || ! is_scalar($value);
    }

    protected function constrain(Builder $query, mixed $value): void
    {
        $query->where($this->qualified($query), $value);
    }
}
