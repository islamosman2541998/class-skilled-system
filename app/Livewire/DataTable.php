<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithTableActions;
use App\Tables\Column;
use App\Tables\Filters\Filter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * The standard list table (spec §5.1). Every list in the system extends this class and
 * only describes its query, columns and filters; search, filtering, date range, sorting,
 * paging and totals behave the same everywhere. All state is kept in the URL.
 */
abstract class DataTable extends Component
{
    use WithPagination, WithTableActions;

    public const PAGE_SIZES = [15, 25, 50, 100];

    public const DATE_PRESETS = ['today', 'this_week', 'this_month', 'last_month', 'this_year'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var array<string, mixed> */
    #[Url(as: 'f', except: [])]
    public array $filters = [];

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: '')]
    public string $sortDir = '';

    #[Url(as: 'per', except: 25)]
    public int $perPage = 25;

    #[Url(as: 'trash', except: false)]
    public bool $trashed = false;

    /** Identifies this table for saved preferences and filters. */
    abstract public function tableKey(): string;

    /**
     * The unfiltered query with its eager loads.
     */
    abstract protected function query(): Builder;

    /**
     * @return list<Column>
     */
    abstract protected function columns(): array;

    /**
     * @return list<Filter>
     */
    protected function filterDefinitions(): array
    {
        return [];
    }

    /**
     * Columns matched by the search box. "relation.column" searches a related model.
     *
     * @return list<string>
     */
    protected function searchColumns(): array
    {
        return [];
    }

    /** The column the "from – to" range applies to; null hides the range. */
    protected function dateColumn(): ?string
    {
        return 'created_at';
    }

    /**
     * @return array{0: string, 1: string} database column and direction
     */
    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /** Permission module of this table ("users" → users.delete …); null makes it read-only. */
    protected function permissionPrefix(): ?string
    {
        return null;
    }

    /** Link opened by the edit action of a row; null hides the action. */
    protected function editUrl(Model $row): ?string
    {
        return null;
    }

    /** Blade partial (receives $row) with extra row actions, shown before edit and delete. */
    protected function rowActionsView(): ?string
    {
        return null;
    }

    public function mount(): void
    {
        if (! request()->has('per') && ($stored = $this->preference()?->per_page)) {
            $this->perPage = $stored;
        }

        $this->normalisePerPage();

        if ($this->trashed && ! $this->canSeeTrash()) {
            $this->trashed = false;
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'perPage') {
            $this->normalisePerPage();
            $this->savePreference(['per_page' => $this->perPage]);
        }

        if ($property === 'trashed' && $this->trashed && ! $this->canSeeTrash()) {
            $this->trashed = false;
        }

        // A selection made under other filters must not leak into the next bulk action.
        if ($property !== 'selected' && $property !== 'perPage' && ! str_starts_with($property, 'selected.')) {
            $this->selected = [];
        }

        if ($property === 'search' || $property === 'perPage' || $property === 'trashed'
            || $property === 'dateFrom' || $property === 'dateTo' || str_starts_with($property, 'filters')) {
            $this->resetPage();
        }
    }

    public function sort(string $key): void
    {
        $column = $this->column($key);

        if ($column === null || $column->sortColumn === null) {
            return;
        }

        $this->sortDir = $this->sortBy === $key && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $key;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filters = [];
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    /** Removes one active chip: "search", "date" or a filter key. */
    public function removeFilter(string $key): void
    {
        if ($key === 'search') {
            $this->search = '';
        } elseif ($key === 'date') {
            $this->dateFrom = '';
            $this->dateTo = '';
        } else {
            unset($this->filters[$key]);
        }

        $this->resetPage();
    }

    public function setDatePreset(string $preset): void
    {
        $today = Carbon::today();

        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            // The UAE working week runs Monday to Friday; the calendar week is Monday to Sunday.
            'this_week' => [$today->copy()->startOfWeek(Carbon::MONDAY), $today->copy()->endOfWeek(Carbon::SUNDAY)],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [null, null],
        };

        $this->setDateRange($from?->toDateString(), $to?->toDateString());
    }

    public function setDateRange(?string $from, ?string $to): void
    {
        $this->dateFrom = (string) $from;
        $this->dateTo = (string) $to;
        $this->resetPage();
    }

    public function render(): View
    {
        $columns = $this->columns();
        $visible = $this->visibleColumns($columns);
        $rows = $this->rows();
        $canDelete = $this->canTable('delete');
        $showTrash = $this->canSeeTrash();

        return view('livewire.data-table', [
            'columns' => $columns,
            'visibleColumns' => $visible,
            'hiddenKeys' => $this->hiddenColumnKeys($columns),
            'savedFilters' => $this->savedFilters(),
            'canDelete' => $canDelete && ! $this->trashed,
            'canRestore' => $showTrash && $this->trashed,
            'canForceDelete' => $this->trashed && $this->canTable('force-delete'),
            'canExport' => $this->canTable('export'),
            'showTrash' => $showTrash,
            'selectable' => $canDelete || $showTrash || $this->canTable('export'),
            'pageIds' => array_map(fn (Model $row) => (string) $row->getKey(), $rows->items()),
            'editUrl' => fn (Model $row) => $this->trashed ? null : $this->editUrl($row),
            'rowActionsView' => $this->rowActionsView(),
            'filterDefinitions' => $this->filterDefinitions(),
            'activeFilters' => $this->activeFilters(),
            'rows' => $rows,
            'totals' => $this->totals($visible),
            'hasDateRange' => $this->dateColumn() !== null,
            'hasSearch' => $this->searchColumns() !== [],
        ]);
    }

    /**
     * Search, filters, date range and trash applied; no ordering and no paging.
     */
    protected function filteredQuery(): Builder
    {
        $query = $this->query();

        if ($this->trashed && $this->canSeeTrash()) {
            $query->onlyTrashed(); // @phpstan-ignore method.notFound
        }

        $this->applySearch($query);

        foreach ($this->filterDefinitions() as $filter) {
            $filter->apply($query, $this->filters[$filter->key] ?? null);
        }

        $this->applyDateRange($query);

        return $query;
    }

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    protected function rows(): LengthAwarePaginator
    {
        $query = $this->filteredQuery();
        $column = $this->sortBy !== '' ? $this->column($this->sortBy) : null;

        if ($column?->sortColumn !== null) {
            $query->reorder($column->sortColumn, $this->sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            [$default, $direction] = $this->defaultSort();
            $query->reorder($default, $direction);
        }

        // A second key keeps the order stable between pages when the first has equal values.
        $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');

        return $query->paginate($this->perPage);
    }

    /**
     * Sums of the summable columns over the whole filtered result.
     *
     * @param  list<Column>  $columns
     * @return array<string, string>
     */
    protected function totals(array $columns): array
    {
        $summable = array_values(array_filter($columns, fn (Column $column) => $column->isSummable));

        if ($summable === []) {
            return [];
        }

        $query = $this->filteredQuery()->reorder()->toBase();
        $grammar = $query->getGrammar();
        $selects = [];

        foreach ($summable as $column) {
            $dbColumn = $column->sortColumn ?? $column->key;
            $selects[] = 'SUM('.$grammar->wrap($dbColumn).') as '.$grammar->wrap($column->key);
        }

        $sums = (array) $query->selectRaw(implode(', ', $selects))->first();

        return collect($summable)
            ->mapWithKeys(fn (Column $column) => [$column->key => money($sums[$column->key] ?? 0, false)])
            ->all();
    }

    /**
     * Chips for everything currently narrowing the result.
     *
     * @return list<array{key: string, label: string, value: string}>
     */
    protected function activeFilters(): array
    {
        $chips = [];

        if ($this->search !== '') {
            $chips[] = ['key' => 'search', 'label' => __('table.search'), 'value' => $this->search];
        }

        foreach ($this->filterDefinitions() as $filter) {
            $value = $this->filters[$filter->key] ?? null;

            if (! $filter->isBlank($value)) {
                $chips[] = ['key' => $filter->key, 'label' => $filter->label(), 'value' => $filter->describe($value)];
            }
        }

        [$from, $to] = $this->dateBounds();

        if ($from !== null || $to !== null) {
            $chips[] = [
                'key' => 'date',
                'label' => __('table.period'),
                'value' => trim(fmt_date($from).' - '.fmt_date($to), ' -'),
            ];
        }

        return $chips;
    }

    protected function column(string $key): ?Column
    {
        foreach ($this->columns() as $column) {
            if ($column->key === $key) {
                return $column;
            }
        }

        return null;
    }

    private function applySearch(Builder $query): void
    {
        $term = trim($this->search);
        $columns = $this->searchColumns();

        if ($term === '' || $columns === []) {
            return;
        }

        // "!" is the escape character so that % and _ typed by the user match literally.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $query->where(function (Builder $outer) use ($columns, $like) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $related] = explode('.', $column, 2);

                    $outer->orWhereHas($relation, function (Builder $inner) use ($related, $like) {
                        $inner->whereRaw($inner->getGrammar()->wrap($inner->qualifyColumn($related))." LIKE ? ESCAPE '!'", [$like]);
                    });
                } else {
                    $outer->orWhereRaw($outer->getGrammar()->wrap($outer->qualifyColumn($column))." LIKE ? ESCAPE '!'", [$like]);
                }
            }
        });
    }

    private function applyDateRange(Builder $query): void
    {
        $column = $this->dateColumn();

        if ($column === null) {
            return;
        }

        [$from, $to] = $this->dateBounds();
        $qualified = $query->qualifyColumn($column);

        // Plain comparisons (not DATE(column)) so the index on the column is used.
        if ($from !== null) {
            $query->where($qualified, '>=', $from->toDateString().' 00:00:00');
        }

        if ($to !== null) {
            $query->where($qualified, '<', $to->copy()->addDay()->toDateString().' 00:00:00');
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function dateBounds(): array
    {
        return [$this->parseDate($this->dateFrom), $this->parseDate($this->dateTo)];
    }

    private function parseDate(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalisePerPage(): void
    {
        if (! in_array($this->perPage, self::PAGE_SIZES, true)) {
            $this->perPage = 25;
        }
    }
}
