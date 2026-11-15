<?php

namespace App\Livewire\Tables\Reports;

use App\Livewire\DataTable;
use App\Models\ReportRow;
use App\Support\ReportRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Base of the report tables (spec §9.2): read-only, exportable, built over an aggregate.
 * The period applies inside the aggregate, not to the aggregated rows.
 */
abstract class ReportTable extends DataTable
{
    protected bool $deletable = false;

    /** The report key is the table key without "reports."; the registry decides who may open it. */
    public function mount(): void
    {
        abort_unless(auth()->user() !== null && ReportRegistry::allows(auth()->user(), substr($this->tableKey(), 8)), 403);

        parent::mount();
    }

    /** The aggregate, already limited to what the user may see and to the period. */
    abstract protected function aggregate(): QueryBuilder;

    protected function query(): Builder
    {
        return ReportRow::query()->fromSub($this->aggregate(), 'r');
    }

    protected function permissionPrefix(): ?string
    {
        return 'reports';
    }

    protected function appliesDateRangeToQuery(): bool
    {
        return false;
    }

    /**
     * The chosen period; an open end stays null.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function period(): array
    {
        return $this->dateBounds();
    }

    /** Limits a date column of the aggregate to the period. */
    protected function withinPeriod(QueryBuilder $query, string $column): QueryBuilder
    {
        [$from, $to] = $this->period();

        return $query
            ->when($from, fn (QueryBuilder $q) => $q->whereDate($column, '>=', $from->toDateString()))
            ->when($to, fn (QueryBuilder $q) => $q->whereDate($column, '<=', $to->toDateString()));
    }

    /** "YYYY-MM" of a date column on MySQL and on SQLite (tests). */
    protected static function monthOf(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    /** Name in the user's language from name_ar / name_en columns selected with a prefix. */
    protected static function localName(object $row, string $prefix): string
    {
        $en = $row->{$prefix.'_en'} ?? null;

        return app()->getLocale() === 'en' && filled($en) ? (string) $en : (string) ($row->{$prefix.'_ar'} ?? '');
    }

    protected function defaultSort(): array
    {
        return ['id', 'desc'];
    }
}
