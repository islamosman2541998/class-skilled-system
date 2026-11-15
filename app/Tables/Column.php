<?php

namespace App\Tables;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * One column of a DataTable: how it is labelled, rendered, sorted, summed and exported.
 */
final class Column
{
    public ?string $sortColumn = null;

    public bool $isSummable = false;

    public bool $showOnCard = false;

    public bool $alignsEnd = false;

    public bool $hiddenByDefault = false;

    /** @var array<string, string> */
    private array $labelParams = [];

    private ?Closure $formatter = null;

    private ?Closure $exporter = null;

    private ?string $view = null;

    private function __construct(public readonly string $key, public readonly string $labelKey) {}

    public static function make(string $key, string $labelKey): self
    {
        return new self($key, $labelKey);
    }

    /** Sort by this database column (defaults to the column key). */
    public function sortable(?string $dbColumn = null): self
    {
        $this->sortColumn = $dbColumn ?? $this->key;

        return $this;
    }

    /** Closure(Model $row): string|Htmlable. Plain strings are escaped by the view. */
    public function format(Closure $formatter): self
    {
        $this->formatter = $formatter;

        return $this;
    }

    /** Blade view receiving $row and $column, for cells that need markup. */
    public function view(string $view): self
    {
        $this->view = $view;

        return $this;
    }

    /** Show the sum of this database column for the whole filtered result. */
    public function summable(): self
    {
        $this->isSummable = true;
        $this->alignsEnd = true;

        return $this;
    }

    /** Include this column in the card shown on small screens. */
    public function onCard(): self
    {
        $this->showOnCard = true;

        return $this;
    }

    public function alignEnd(): self
    {
        $this->alignsEnd = true;

        return $this;
    }

    public function hiddenByDefault(): self
    {
        $this->hiddenByDefault = true;

        return $this;
    }

    /** Closure(Model $row): scalar value written to Excel. Defaults to the formatted text. */
    public function exportAs(Closure $exporter): self
    {
        $this->exporter = $exporter;

        return $this;
    }

    /**
     * Placeholders for the label, for a column made per lookup (one column per expense category).
     *
     * @param  array<string, string>  $params
     */
    public function labelWith(array $params): self
    {
        $this->labelParams = $params;

        return $this;
    }

    public function label(): string
    {
        return __($this->labelKey, $this->labelParams);
    }

    public function render(Model $row): Htmlable|string
    {
        if ($this->view !== null) {
            return new HtmlString(view($this->view, ['row' => $row, 'column' => $this])->render());
        }

        if ($this->formatter !== null) {
            $value = ($this->formatter)($row);

            return $value instanceof Htmlable ? $value : (string) $value;
        }

        return (string) data_get($row, $this->key);
    }

    public function exportValue(Model $row): string|int|float|null
    {
        if ($this->exporter !== null) {
            return ($this->exporter)($row);
        }

        if ($this->formatter !== null) {
            $value = ($this->formatter)($row);

            return trim(html_entity_decode(strip_tags($value instanceof Htmlable ? $value->toHtml() : (string) $value)));
        }

        $value = data_get($row, $this->key);

        return is_scalar($value) || $value === null ? $value : (string) $value;
    }
}
