<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Livewire\DataTable;
use App\Models\Lookup;
use App\Models\PriceItem;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\NumberRangeFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class PriceItemsTable extends DataTable
{
    public function tableKey(): string
    {
        return 'price_items';
    }

    protected function permissionPrefix(): ?string
    {
        return 'price_items';
    }

    /** Refreshes after the add / edit modal saved. */
    #[On('table-refresh')]
    public function refreshRows(): void {}

    protected function query(): Builder
    {
        return PriceItem::query()->with(['unit', 'service']);
    }

    protected function columns(): array
    {
        return [
            Column::make('description_ar', 'price_items.description')->sortable()->format(fn (PriceItem $row) => $row->description)->onCard(),
            Column::make('service', 'price_items.service')->format(fn (PriceItem $row) => (string) $row->service?->name),
            Column::make('unit', 'price_items.unit')->format(fn (PriceItem $row) => $row->unit->name)->onCard(),
            Column::make('default_price', 'price_items.price')->sortable()->alignEnd()->format(fn (PriceItem $row) => money($row->default_price))
                ->exportAs(fn (PriceItem $row) => $row->default_price)->onCard(),
            Column::make('is_active', 'users.status')->sortable()->view('lookups.partials.status')
                ->exportAs(fn (PriceItem $row) => $row->is_active ? __('users.active') : __('users.inactive')),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('service_id', 'price_items.service')->options(fn () => Lookup::options(LookupType::Service)->pluck('name', 'id')),
            SelectFilter::make('unit_id', 'price_items.unit')->options(fn () => Lookup::options(LookupType::Unit)->pluck('name', 'id')),
            BooleanFilter::make('is_active', 'users.status'),
            NumberRangeFilter::make('default_price', 'price_items.price'),
        ];
    }

    protected function searchColumns(): array
    {
        return ['description_ar', 'description_en'];
    }

    protected function rowActionsView(): ?string
    {
        return auth()->user()?->can('price_items.update') ? 'price-items.partials.row-actions' : null;
    }
}
