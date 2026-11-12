<?php

namespace App\Livewire\Concerns;

use App\Actions\DeleteRecords;
use App\Models\SavedFilter;
use App\Models\TablePreference;
use App\Services\TableExporter;
use App\Tables\Column;
use App\Tables\DeleteResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Row selection, delete (one / selected / all), trash, Excel export, column visibility and
 * saved filters for DataTable. Every action is authorized against "{prefix}.{action}".
 */
trait WithTableActions
{
    /** @var list<string> */
    public array $selected = [];

    /** False makes the table read-only: no delete, restore or permanent delete. */
    protected bool $deletable = true;

    private ?TablePreference $preferenceCache = null;

    private bool $preferenceLoaded = false;

    public function deleteOne(int|string $id): void
    {
        $this->authorizeTable('delete');

        $this->reportDeletion($this->deleteQuery($this->query()->whereKey($id)));
    }

    public function deleteSelected(): void
    {
        $this->authorizeTable('delete');

        if ($this->selected === [] || $this->trashed) {
            return;
        }

        $this->reportDeletion($this->deleteQuery($this->filteredQuery()->whereKey($this->selected)));
        $this->selected = [];
    }

    /** Deletes everything matching the current search and filters, on every page. */
    public function deleteAll(): void
    {
        $this->authorizeTable('delete');

        if ($this->trashed) {
            return;
        }

        $this->reportDeletion($this->deleteQuery($this->filteredQuery()));
        $this->selected = [];
        $this->resetPage();
    }

    public function restore(int|string $id): void
    {
        $this->authorizeTable('restore');

        $record = $this->trashedQuery()->whereKey($id)->first();

        if ($record !== null) {
            $record->restore(); // @phpstan-ignore method.notFound
            $this->dispatch('toast', type: 'success', message: __('table.restored', ['count' => 1]));
        }
    }

    public function restoreSelected(): void
    {
        $this->authorizeTable('restore');

        $count = 0;

        foreach ($this->trashedQuery()->whereKey($this->selected)->get() as $record) {
            $record->restore(); // @phpstan-ignore method.notFound
            $count++;
        }

        $this->selected = [];

        if ($count > 0) {
            $this->dispatch('toast', type: 'success', message: __('table.restored', ['count' => $count]));
        }
    }

    /** Permanent delete; only for a record that is already in the trash. */
    public function forceDelete(int|string $id): void
    {
        $this->authorizeTable('force-delete');

        $record = $this->trashedQuery()->whereKey($id)->first();

        if ($record !== null) {
            $record->forceDelete();
            $this->dispatch('toast', type: 'success', message: __('table.force_deleted'));
        }
    }

    public function export(): BinaryFileResponse
    {
        $this->authorizeTable('export');

        return $this->exportQuery($this->filteredQuery());
    }

    public function exportSelected(): BinaryFileResponse
    {
        $this->authorizeTable('export');

        return $this->exportQuery($this->filteredQuery()->whereKey($this->selected));
    }

    /** Selects every row of the current page, or clears them when all are already selected. */
    public function togglePageSelection(): void
    {
        $pageIds = array_map('strval', $this->rows()->getCollection()->modelKeys());
        $allSelected = $pageIds !== [] && array_diff($pageIds, $this->selected) === [];

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique([...$this->selected, ...$pageIds]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function toggleColumn(string $key): void
    {
        $columns = $this->columns();
        $keys = array_map(fn (Column $column) => $column->key, $columns);

        if (! in_array($key, $keys, true)) {
            return;
        }

        $hidden = $this->hiddenColumnKeys($columns);
        $hidden = in_array($key, $hidden, true) ? array_diff($hidden, [$key]) : [...$hidden, $key];
        $hidden = array_values(array_intersect($keys, $hidden));

        // At least one column always stays visible.
        if (count($hidden) >= count($keys)) {
            return;
        }

        $this->savePreference(['hidden_columns' => $hidden]);
    }

    public function saveFilter(string $name): void
    {
        $name = trim($name);

        if ($name === '' || ! auth()->check()) {
            return;
        }

        SavedFilter::create([
            'user_id' => auth()->id(),
            'table_key' => $this->tableKey(),
            'name' => mb_substr($name, 0, 80),
            'state' => [
                'search' => $this->search,
                'filters' => $this->filters,
                'dateFrom' => $this->dateFrom,
                'dateTo' => $this->dateTo,
                'sortBy' => $this->sortBy,
                'sortDir' => $this->sortDir,
            ],
        ]);

        $this->dispatch('toast', type: 'success', message: __('table.filter_saved'));
    }

    public function applySavedFilter(int $id): void
    {
        $saved = $this->savedFilters()->firstWhere('id', $id);

        if ($saved === null) {
            return;
        }

        $state = $saved->state;

        $this->search = (string) ($state['search'] ?? '');
        $this->filters = (array) ($state['filters'] ?? []);
        $this->dateFrom = (string) ($state['dateFrom'] ?? '');
        $this->dateTo = (string) ($state['dateTo'] ?? '');
        $this->sortBy = (string) ($state['sortBy'] ?? '');
        $this->sortDir = (string) ($state['sortDir'] ?? '');
        $this->selected = [];
        $this->resetPage();
    }

    public function deleteSavedFilter(int $id): void
    {
        $this->savedFilters()->firstWhere('id', $id)?->delete();
    }

    /**
     * @return Collection<int, SavedFilter>
     */
    protected function savedFilters(): Collection
    {
        if (! auth()->check()) {
            return new Collection;
        }

        return SavedFilter::query()
            ->where('user_id', auth()->id())
            ->where('table_key', $this->tableKey())
            ->orderBy('name')
            ->get();
    }

    protected function canTable(string $action): bool
    {
        $prefix = $this->permissionPrefix();

        if ($prefix === null) {
            return false;
        }

        if (! $this->deletable && in_array($action, ['delete', 'restore', 'force-delete'], true)) {
            return false;
        }

        return Gate::allows("{$prefix}.{$action}");
    }

    protected function authorizeTable(string $action): void
    {
        abort_unless($this->canTable($action), 403);
    }

    protected function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->query()->getModel()), true);
    }

    protected function canSeeTrash(): bool
    {
        return $this->usesSoftDeletes() && $this->canTable('restore');
    }

    /**
     * @param  list<Column>  $columns
     * @return list<Column>
     */
    protected function visibleColumns(array $columns): array
    {
        $hidden = $this->hiddenColumnKeys($columns);

        return array_values(array_filter($columns, fn (Column $column) => ! in_array($column->key, $hidden, true)));
    }

    /**
     * @param  list<Column>  $columns
     * @return list<string>
     */
    protected function hiddenColumnKeys(array $columns): array
    {
        $stored = $this->preference()?->hidden_columns;

        if ($stored !== null) {
            return $stored;
        }

        return array_values(array_map(
            fn (Column $column) => $column->key,
            array_filter($columns, fn (Column $column) => $column->hiddenByDefault),
        ));
    }

    protected function preference(): ?TablePreference
    {
        if (! $this->preferenceLoaded) {
            $this->preferenceLoaded = true;
            $this->preferenceCache = auth()->check()
                ? TablePreference::query()->where('user_id', auth()->id())->where('table_key', $this->tableKey())->first()
                : null;
        }

        return $this->preferenceCache;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function savePreference(array $values): void
    {
        if (! auth()->check()) {
            return;
        }

        $this->preferenceCache = TablePreference::updateOrCreate(
            ['user_id' => auth()->id(), 'table_key' => $this->tableKey()],
            $values,
        );
        $this->preferenceLoaded = true;
    }

    /**
     * @return Builder<Model>
     */
    private function trashedQuery(): Builder
    {
        abort_unless($this->usesSoftDeletes(), 403);

        return $this->query()->onlyTrashed(); // @phpstan-ignore method.notFound
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function deleteQuery(Builder $query): DeleteResult
    {
        return app(DeleteRecords::class)->handle($query, auth()->user());
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function exportQuery(Builder $query): BinaryFileResponse
    {
        return app(TableExporter::class)->download(
            $this->tableKey().'.xlsx',
            $query,
            $this->visibleColumns($this->columns()),
        );
    }

    private function reportDeletion(DeleteResult $result): void
    {
        if ($result->deleted > 0) {
            $this->dispatch('toast', type: 'success', message: __('table.deleted', ['count' => $result->deleted]));
        }

        if ($result->skipped > 0) {
            $this->dispatch('toast', type: 'warning', message: __('table.skipped', [
                'count' => $result->skipped,
                'reason' => $result->firstReason,
            ]));
        }
    }
}
