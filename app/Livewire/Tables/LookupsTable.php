<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Livewire\DataTable;
use App\Models\Lookup;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

/**
 * One table for every managed list; `type` selects the list.
 */
class LookupsTable extends DataTable
{
    #[Locked]
    public string $type = '';

    public function tableKey(): string
    {
        return 'lookups.'.$this->type;
    }

    protected function permissionPrefix(): ?string
    {
        return 'lookups';
    }

    /** Refreshes after the add/edit modal or the reorder dialog saved. */
    #[On('table-refresh')]
    public function refreshRows(): void {}

    protected function query(): Builder
    {
        return Lookup::query()->ofType($this->listType())->with('parent');
    }

    protected function columns(): array
    {
        $type = $this->listType();

        $columns = [
            Column::make('name_ar', 'settings.name_ar')->sortable()->onCard(),
            Column::make('name_en', 'settings.name_en')->sortable(),
        ];

        if ($type->parentType() !== null) {
            $columns[] = Column::make('parent', 'settings.parent')->format(fn (Lookup $row) => (string) $row->parent?->name)->onCard();
        }

        if ($type->hasColor()) {
            $columns[] = Column::make('color', 'settings.color')->view('lookups.partials.color');
        }

        $columns[] = Column::make('is_active', 'users.status')->sortable()->view('lookups.partials.status')
            ->exportAs(fn (Lookup $row) => $row->is_active ? __('users.active') : __('users.inactive'))->onCard();
        $columns[] = Column::make('sort_order', 'settings.sort_order')->sortable()->alignEnd();

        return $columns;
    }

    protected function filterDefinitions(): array
    {
        $filters = [BooleanFilter::make('is_active', 'users.status')];
        $parentType = $this->listType()->parentType();

        if ($parentType !== null) {
            $filters[] = SelectFilter::make('parent_id', 'settings.parent')->options(fn () => Lookup::options($parentType)->pluck('name', 'id'));
        }

        return $filters;
    }

    protected function searchColumns(): array
    {
        return ['name_ar', 'name_en'];
    }

    protected function dateColumn(): ?string
    {
        return null;
    }

    protected function defaultSort(): array
    {
        return ['sort_order', 'asc'];
    }

    protected function rowActionsView(): ?string
    {
        return auth()->user()?->can('lookups.update') ? 'lookups.partials.row-actions' : null;
    }

    private function listType(): LookupType
    {
        return LookupType::from($this->type);
    }
}
